#!/usr/bin/env python3
import io
import json
import os
import plistlib
import re
import struct
import sys
import urllib.request
import zipfile

BLOCK = 256 * 1024
DEFAULT_MAX_FETCH = 24 * 1024 * 1024
TIMEOUT = 20
LAST_METRICS = {'range_bytes': 0, 'range_requests': 0}
CPU_TYPES = {7:'x86',0x01000007:'x86_64',12:'arm',0x0100000C:'arm64',18:'ppc',0x01000012:'ppc64'}

class HttpRangeFile(io.RawIOBase):
    def __init__(self,url,size,max_fetch=DEFAULT_MAX_FETCH): self.url=str(url);self.size=int(size);self.pos=0;self.cache={};self.fetched=0;self.requests=0;self.max_fetch=int(max_fetch)
    def readable(self): return True
    def seekable(self): return True
    def tell(self): return self.pos
    def seek(self,offset,whence=io.SEEK_SET):
        if whence==io.SEEK_SET: pos=offset
        elif whence==io.SEEK_CUR: pos=self.pos+offset
        elif whence==io.SEEK_END: pos=self.size+offset
        else: raise ValueError('invalid whence')
        self.pos=max(0,min(self.size,int(pos)));return self.pos
    def _block(self,index):
        if index in self.cache:return self.cache[index]
        start=index*BLOCK;end=min(self.size-1,start+BLOCK-1)
        if start>end:return b''
        expected=end-start+1
        if self.fetched+expected>self.max_fetch:raise RuntimeError('IPA Range 读取超过安全上限')
        req=urllib.request.Request(self.url,headers={'Range':f'bytes={start}-{end}','User-Agent':'zonoe-phase20-ipa-parser/1.0','Accept-Encoding':'identity'})
        self.requests+=1;LAST_METRICS['range_requests']=self.requests
        with urllib.request.urlopen(req,timeout=TIMEOUT) as res:
            status=getattr(res,'status',None) or res.getcode()
            if status!=206:raise RuntimeError(f'远端不支持 HTTP Range (HTTP {status})')
            data=res.read(expected)
        self.fetched+=len(data);LAST_METRICS['range_bytes']=self.fetched;self.cache[index]=data;return data
    def read(self,n=-1):
        if self.pos>=self.size:return b''
        if n is None or n<0:n=self.size-self.pos
        n=min(int(n),self.size-self.pos);out=bytearray()
        while n>0:
            idx=self.pos//BLOCK;block=self._block(idx);off=self.pos%BLOCK;take=min(n,max(0,len(block)-off))
            if take<=0:break
            out.extend(block[off:off+take]);self.pos+=take;n-=take
        return bytes(out)

def clean(v): return str(v).strip() if isinstance(v,(str,int,float)) else ''
def listify(v): return v if isinstance(v,list) else ([] if v is None else [v])
def choose_main_app_info(zf):
    items=[x for x in zf.infolist() if re.match(r'^Payload/[^/]+\.app/Info\.plist$',x.filename,re.I)]
    if not items:raise RuntimeError('IPA 中未找到 Payload/*.app/Info.plist')
    return sorted(items,key=lambda x:(len(x.filename),x.filename.lower()))[0]
def read_small_entry(zf,info,limit):
    if info.file_size>limit:raise RuntimeError(f'{info.filename} 异常过大')
    return zf.read(info)
def plist_load(raw,label='plist'):
    try:obj=plistlib.loads(raw)
    except Exception as e:raise RuntimeError(f'{label} 解析失败: {e}')
    if not isinstance(obj,dict):raise RuntimeError(f'{label} 顶层不是字典')
    return obj

def declared_icon_names(plist):
    names=[]
    def add(value):
        for x in listify(value):
            s=clean(x)
            if s and s not in names:names.append(s)
    add(plist.get('CFBundleIconFiles'));add(plist.get('CFBundleIconName'))
    for key in ('CFBundleIcons','CFBundleIcons~ipad'):
        root=plist.get(key)
        if not isinstance(root,dict):continue
        primary=root.get('CFBundlePrimaryIcon')
        if isinstance(primary,dict):add(primary.get('CFBundleIconFiles'));add(primary.get('CFBundleIconName'))
        alternates=root.get('CFBundleAlternateIcons')
        if isinstance(alternates,dict):
            for alt in alternates.values():
                if isinstance(alt,dict):add(alt.get('CFBundleIconFiles'));add(alt.get('CFBundleIconName'))
    return names

def png_dimensions(data):
    if len(data)<24 or data[:8]!=b'\x89PNG\r\n\x1a\n':return None
    if data[12:16]!=b'IHDR':
        idx=data.find(b'IHDR')
        if idx<0 or idx+12>len(data):return None
        off=idx+4
    else:off=16
    try:
        w,h=struct.unpack('>II',data[off:off+8])
        if 0<w<=8192 and 0<h<=8192:return [int(w),int(h)]
    except Exception:pass
    return None

def icon_candidates(zf,app_prefix,plist):
    declared=declared_icon_names(plist);all_png=[x for x in zf.infolist() if x.filename.lower().startswith(app_prefix.lower()) and x.filename.lower().endswith('.png')];candidates=[]
    for info in all_png:
        base=os.path.basename(info.filename);stem=base[:-4];declared_match=False
        if declared:
            for name in declared:
                n=os.path.basename(name);nstem=n[:-4] if n.lower().endswith('.png') else n
                if stem==nstem or stem.startswith(nstem+'@') or stem.startswith(nstem+'~') or stem.startswith(nstem+'-'):declared_match=True;break
        else:declared_match='icon' in base.lower()
        if not declared_match:continue
        dims=None
        try:
            with zf.open(info,'r') as fh:dims=png_dimensions(fh.read(128))
        except Exception:pass
        candidates.append({'entry':info.filename,'name':base,'width':dims[0] if dims else 0,'height':dims[1] if dims else 0,'declared':True,'ipad':'~ipad' in base.lower() or 'ipad' in base.lower()})
    def score(x):
        w,h=x['width'],x['height'];square=0 if (w and h and w==h) else 1;iphone=1 if x['ipad'] else 0;eligible=0 if (w>=120 and h>=120) else 1;area=w*h if w and h else 10**12;return (iphone,eligible,square,area,x['name'].lower())
    candidates.sort(key=score);return candidates[:50],(candidates[0] if candidates else None)

def mach_arches(data):
    if len(data)<8:return []
    magic=data[:4];out=[]
    def add_cpu(cputype):
        name=CPU_TYPES.get(cputype&0xFFFFFFFF,f'cpu_{cputype&0xFFFFFFFF}')
        if name not in out:out.append(name)
    if magic in (b'\xfe\xed\xfa\xce',b'\xfe\xed\xfa\xcf'):add_cpu(struct.unpack('>I',data[4:8])[0])
    elif magic in (b'\xce\xfa\xed\xfe',b'\xcf\xfa\xed\xfe'):add_cpu(struct.unpack('<I',data[4:8])[0])
    elif magic in (b'\xca\xfe\xba\xbe',b'\xca\xfe\xba\xbf'):
        nfat=struct.unpack('>I',data[4:8])[0];off=8;entry_size=24 if magic==b'\xca\xfe\xba\xbf' else 20
        for _ in range(min(nfat,16)):
            if off+entry_size>len(data):break
            add_cpu(struct.unpack('>I',data[off:off+4])[0]);off+=entry_size
    elif magic in (b'\xbe\xba\xfe\xca',b'\xbf\xba\xfe\xca'):
        nfat=struct.unpack('<I',data[4:8])[0];off=8;entry_size=24 if magic==b'\xbf\xba\xfe\xca' else 20
        for _ in range(min(nfat,16)):
            if off+entry_size>len(data):break
            add_cpu(struct.unpack('<I',data[off:off+4])[0]);off+=entry_size
    return out

def app_arches(zf,app_prefix,executable):
    if not executable:return []
    try:info=zf.getinfo(app_prefix+executable)
    except KeyError:return []
    try:
        with zf.open(info,'r') as fh:return mach_arches(fh.read(4096))
    except Exception:return []

def extract_mobileprovision(zf,app_prefix):
    try:info=zf.getinfo(app_prefix+'embedded.mobileprovision')
    except KeyError:return {}
    if info.file_size>4*1024*1024:return {'present':True,'error':'embedded.mobileprovision too large'}
    try:
        raw=zf.read(info);start=raw.find(b'<?xml');end=raw.find(b'</plist>')
        if start<0 or end<0:return {'present':True}
        plist=plistlib.loads(raw[start:end+len(b'</plist>')]);ent=plist.get('Entitlements') if isinstance(plist.get('Entitlements'),dict) else {};expiration=plist.get('ExpirationDate')
        return {'present':True,'team_id':clean((plist.get('TeamIdentifier') or [''])[0] if isinstance(plist.get('TeamIdentifier'),list) else plist.get('TeamIdentifier')),'app_identifier':clean(ent.get('application-identifier')),'get_task_allow':bool(ent.get('get-task-allow',False)),'aps_environment':clean(ent.get('aps-environment')),'app_groups':[clean(x) for x in listify(ent.get('com.apple.security.application-groups')) if clean(x)],'keychain_groups':[clean(x) for x in listify(ent.get('keychain-access-groups')) if clean(x)],'expiration':expiration.isoformat() if hasattr(expiration,'isoformat') else clean(expiration)}
    except Exception as e:return {'present':True,'error':str(e)[:200]}
def url_schemes(plist):
    out=[]
    for item in listify(plist.get('CFBundleURLTypes')):
        if not isinstance(item,dict):continue
        for value in listify(item.get('CFBundleURLSchemes')):
            value=clean(value)
            if value and value not in out:out.append(value)
    return out

def parse_zip(zf):
    info_item=choose_main_app_info(zf);plist=plist_load(read_small_entry(zf,info_item,4*1024*1024),'Info.plist');app_prefix=info_item.filename[:-len('Info.plist')];executable=clean(plist.get('CFBundleExecutable'));icons,primary_icon=icon_candidates(zf,app_prefix,plist);names=[x.filename for x in zf.infolist()]
    appex=sorted({re.match(r'^(Payload/[^/]+\.app/PlugIns/[^/]+\.appex)/',n,re.I).group(1) for n in names if re.match(r'^(Payload/[^/]+\.app/PlugIns/[^/]+\.appex)/',n,re.I)})
    frameworks=sorted({re.match(r'^(Payload/[^/]+\.app/Frameworks/[^/]+\.framework)/',n,re.I).group(1) for n in names if re.match(r'^(Payload/[^/]+\.app/Frameworks/[^/]+\.framework)/',n,re.I)})
    dylibs=sorted([n for n in names if n.lower().startswith((app_prefix+'Frameworks/').lower()) and n.lower().endswith('.dylib')])[:200]
    capabilities=plist.get('UIRequiredDeviceCapabilities');capabilities=capabilities if isinstance(capabilities,dict) else [clean(x) for x in listify(capabilities) if clean(x)]
    raw_selected={k:plist.get(k) for k in ['CFBundleDisplayName','CFBundleName','CFBundleIdentifier','CFBundleShortVersionString','CFBundleVersion','CFBundleExecutable','MinimumOSVersion','UIDeviceFamily','UIRequiredDeviceCapabilities','CFBundleDevelopmentRegion','CFBundleLocalizations','CFBundleURLTypes','LSApplicationQueriesSchemes','NSAppTransportSecurity','CFBundleIcons','CFBundleIcons~ipad','CFBundleIconFiles','CFBundleIconName']}
    return {'name':clean(plist.get('CFBundleDisplayName')) or clean(plist.get('CFBundleName')),'bundle_id':clean(plist.get('CFBundleIdentifier')),'version':clean(plist.get('CFBundleShortVersionString')),'build':clean(plist.get('CFBundleVersion')),'minimum_ios':clean(plist.get('MinimumOSVersion')),'executable':executable,'development_region':clean(plist.get('CFBundleDevelopmentRegion')),'localizations':[clean(x) for x in listify(plist.get('CFBundleLocalizations')) if clean(x)],'device_family':[int(x) for x in listify(plist.get('UIDeviceFamily')) if str(x).isdigit()],'required_capabilities':capabilities,'url_schemes':url_schemes(plist),'query_schemes':[clean(x) for x in listify(plist.get('LSApplicationQueriesSchemes')) if clean(x)],'ats':plist.get('NSAppTransportSecurity') if isinstance(plist.get('NSAppTransportSecurity'),dict) else {},'architectures':app_arches(zf,app_prefix,executable),'icons':icons,'primary_icon':primary_icon,'extensions':appex[:100],'frameworks':frameworks[:200],'dylibs':dylibs,'swift':any('/Frameworks/libswift' in n or '/Frameworks/Swift' in n for n in names),'provisioning':extract_mobileprovision(zf,app_prefix),'payload_app_path':app_prefix[:-1],'info_plist_path':info_item.filename,'raw_selected':raw_selected}

def run_remote(req):
    url=str(req.get('url') or '');size=int(req.get('size') or 0);max_fetch=int(req.get('max_fetch') or DEFAULT_MAX_FETCH)
    if not url.startswith(('http://','https://')) or size<=0:raise RuntimeError('url/size 参数无效')
    remote=HttpRangeFile(url,size,max_fetch=max_fetch)
    with zipfile.ZipFile(remote,'r') as zf:parsed=parse_zip(zf)
    parsed['range_bytes']=remote.fetched;parsed['range_requests']=remote.requests;parsed['range_limit']=remote.max_fetch;return parsed

def self_test():
    import tempfile
    plist={'CFBundleDisplayName':'Demo App','CFBundleName':'Demo','CFBundleIdentifier':'com.example.demo','CFBundleShortVersionString':'1.2.3','CFBundleVersion':'123','CFBundleExecutable':'Demo','MinimumOSVersion':'15.0','UIDeviceFamily':[1,2],'CFBundleURLTypes':[{'CFBundleURLSchemes':['demo']}],'CFBundleIconFiles':['AppIcon60x60']}
    png=b'\x89PNG\r\n\x1a\n'+b'\x00\x00\x00\rIHDR'+struct.pack('>II',180,180)+b'\x08\x06\x00\x00\x00'+b'\x00'*32
    with tempfile.NamedTemporaryFile(suffix='.ipa') as tmp:
        with zipfile.ZipFile(tmp.name,'w',zipfile.ZIP_DEFLATED) as z:
            z.writestr('Payload/Demo.app/Info.plist',plistlib.dumps(plist,fmt=plistlib.FMT_BINARY));z.writestr('Payload/Demo.app/AppIcon60x60@3x.png',png);z.writestr('Payload/Demo.app/Demo',b'\xcf\xfa\xed\xfe'+struct.pack('<I',0x0100000C)+b'\0'*100);z.writestr('Payload/Demo.app/Frameworks/libswiftCore.dylib',b'')
        with zipfile.ZipFile(tmp.name,'r') as z:result=parse_zip(z)
    assert result['name']=='Demo App' and result['bundle_id']=='com.example.demo' and result['version']=='1.2.3' and result['architectures']==['arm64'] and result['primary_icon']['width']==180 and result['url_schemes']==['demo'] and result['swift'] is True
    print('OK ipa-range-info self-test')

def main():
    if '--self-test' in sys.argv:self_test();return
    parsed=run_remote(json.loads(sys.stdin.read() or '{}'));print(json.dumps({'ok':True,'metadata':parsed},ensure_ascii=False,separators=(',',':')))
if __name__=='__main__':
    try:main()
    except Exception as e:
        print(json.dumps({'ok':False,'error':str(e)[:500],'range_bytes':int(LAST_METRICS.get('range_bytes') or 0),'range_requests':int(LAST_METRICS.get('range_requests') or 0)},ensure_ascii=False,separators=(',',':')));sys.exit(1)
