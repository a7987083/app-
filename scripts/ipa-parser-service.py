#!/usr/bin/env python3
"""Persistent loopback RPC service for the ZONOE IPA Range parser.

The mature parser stays in ipa-range-info.py. This process imports it once and
serves newline-delimited JSON requests over localhost, so PHP never needs to
spawn Python with proc_open/exec/shell_exec. When an online update replaces
this service or the parser source, the process re-execs itself on the next
request so future updates do not require a manual systemctl restart.
"""
import argparse
import importlib.util
import json
import os
import socketserver
import sys

SERVICE_NAME = 'zonoe-ipa-parser'
SERVICE_VERSION = '1'
MAX_REQUEST_BYTES = 64 * 1024
SERVICE_PATH = os.path.abspath(__file__)
PARSER_PATH = os.path.join(os.path.dirname(SERVICE_PATH), 'ipa-range-info.py')


def source_signature():
    out = []
    for path in (SERVICE_PATH, PARSER_PATH):
        st = os.stat(path)
        out.append((path, getattr(st, 'st_mtime_ns', int(st.st_mtime * 1000000000)), st.st_size))
    return tuple(out)


START_SIGNATURE = source_signature()
_spec = importlib.util.spec_from_file_location('zonoe_ipa_range_info', PARSER_PATH)
if _spec is None or _spec.loader is None:
    raise RuntimeError('cannot load ipa-range-info.py')
parser = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(parser)


def maybe_reload():
    if source_signature() != START_SIGNATURE:
        sys.stderr.write('ZONOE IPA parser source changed; reloading process\n')
        sys.stderr.flush()
        os.execv(sys.executable, [sys.executable] + sys.argv)


def response_for(req):
    if not isinstance(req, dict):
        raise RuntimeError('request must be a JSON object')
    op = str(req.get('op') or 'parse')
    if op == 'health':
        return {'ok': True, 'service': SERVICE_NAME, 'version': SERVICE_VERSION, 'pid': os.getpid()}
    if op != 'parse':
        raise RuntimeError('unsupported op: ' + op)
    parser.LAST_METRICS['range_bytes'] = 0
    parser.LAST_METRICS['range_requests'] = 0
    metadata = parser.run_remote(req)
    return {'ok': True, 'metadata': metadata, 'service': SERVICE_NAME, 'version': SERVICE_VERSION}


def error_response(exc):
    return {
        'ok': False,
        'error': str(exc)[:500],
        'range_bytes': int(parser.LAST_METRICS.get('range_bytes') or 0),
        'range_requests': int(parser.LAST_METRICS.get('range_requests') or 0),
        'service': SERVICE_NAME,
        'version': SERVICE_VERSION,
    }


class ParserHandler(socketserver.StreamRequestHandler):
    def handle(self):
        maybe_reload()
        raw = self.rfile.readline(MAX_REQUEST_BYTES + 1)
        if len(raw) > MAX_REQUEST_BYTES:
            payload = error_response(RuntimeError('request too large'))
        else:
            try:
                req = json.loads(raw.decode('utf-8') or '{}')
                payload = response_for(req)
            except Exception as exc:
                payload = error_response(exc)
        data = json.dumps(payload, ensure_ascii=False, separators=(',', ':')).encode('utf-8') + b'\n'
        self.wfile.write(data)
        self.wfile.flush()


class ParserServer(socketserver.TCPServer):
    allow_reuse_address = True


def serve(host, port):
    if host not in ('127.0.0.1', 'localhost'):
        raise RuntimeError('parser service must bind to IPv4 loopback only')
    with ParserServer((host, int(port)), ParserHandler) as server:
        sys.stderr.write('ZONOE IPA parser service listening on %s:%d\n' % (host, int(port)))
        sys.stderr.flush()
        server.serve_forever(poll_interval=0.5)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--host', default=os.environ.get('ZONOE_IPA_PARSER_HOST', '127.0.0.1'))
    ap.add_argument('--port', type=int, default=int(os.environ.get('ZONOE_IPA_PARSER_PORT', '19191')))
    ap.add_argument('--health-self-test', action='store_true')
    args = ap.parse_args()
    if args.health_self_test:
        result = response_for({'op': 'health'})
        assert result.get('ok') is True and result.get('service') == SERVICE_NAME
        print('OK ipa-parser-service self-test')
        return
    if not (1 <= args.port <= 65535):
        raise RuntimeError('invalid parser service port')
    serve(args.host, args.port)


if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        pass
    except Exception as exc:
        sys.stderr.write(str(exc) + '\n')
        sys.exit(1)
