<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Code;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class CodeController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('authenticated_agent');

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));
        $keyword = $request->input('keyword');
        $status = $request->input('status');
        $type = $request->input('type');
        $product = $request->input('product');

        $query = Code::query()->where('agent_id', $agent->id);

        if (! empty($keyword)) {
            $kw = trim((string) $keyword);
            $query->where(function ($q) use ($kw) {
                $q->where('code', 'like', '%'.$kw.'%')
                    ->orWhere('udid', 'like', '%'.$kw.'%')
                    ->orWhere('remark', 'like', '%'.$kw.'%');
            });
        }
        if (! empty($status)) {
            $query->where('status', $status);
        }
        if (! empty($type)) {
            $query->where('type', $type);
        }
        if (! empty($product)) {
            $query->where('product', $product);
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);
        $items = collect($paginator->items())->map(fn ($item) => $this->mapCodeItem($item))->values()->all();

        return $this->autoRespond(200, '获取成功', [
                'items' => $items,
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
    }

    public function store(Request $request): Response
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('authenticated_agent');

        $validator = Validator::make($request->all(), [
            'number' => 'nullable|integer|min:1|max:10000',
            'after_sale_day' => 'nullable|integer|min:0',
            'after_sale_num' => 'nullable|integer|min:0',
            'remark' => 'nullable|string|max:65535',
            'prefix' => 'nullable|string|max:64',
            'codeType' => 'nullable|in:default,good,processing',
            'codeProduct' => 'nullable|in:DEFAULT,IPAD',
        ]);
        if ($validator->fails()) {
            return $this->autoRespond(422, $validator->errors()->first(), [
                'errors' => $validator->errors()
            ]);
        }

        $type = $request->input('codeType', 'default');
        $product = $request->input('codeProduct', 'DEFAULT');
        $number = (int) ($request->input('number') ?: 1);
        $remark = $request->input('remark');
        $after_sale_day = (int) ($request->input('after_sale_day') ?? 365);
        $after_sale_num = (int) ($request->input('after_sale_num') ?? 0);
        $prefix = $request->input('prefix') ?: config('api.basics.code_generate_prefix');

        $gate = $this->assertTypeGenerationAllowed($type);
        if ($gate !== null) {
            return $gate;
        }

        if ($number <= 0) {
            $number = 1;
        }
        if ($number > 10000) {
            return $this->autoRespond(400, '单次生成不能超过 10000 个');
        }

        $date = date('Y-m-d H:i:s');
        $uuids = [];
        $codes = [];
        for ($i = 0; $i < $number; $i++) {
            $uuid = customCode($prefix);
            $uuids[] = $uuid;
            $codes[] = [
                'remark' => $remark,
                'after_sale_day' => $after_sale_day,
                'after_sale_num' => $after_sale_num,
                'use_after_sale' => '0',
                'code' => $uuid,
                'created_at' => $date,
                'type' => $type,
                'product' => $product,
                'agent_id' => $agent->id,
            ];
        }

        try {
            Code::insert($codes);
            $uuidsString = implode("\n", $uuids);
            return $this->autoRespond(201, '添加成功', [
                'number' => $number,
                'after_sale_day' => $after_sale_day,
                'after_sale_num' => $after_sale_num,
                'remark' => $remark,
                'product' => ($product === 'DEFAULT') ? '全部机型' : '仅限iPad',
                'codes' => base64_encode($uuidsString),
                'codes_plain' => $uuidsString,
            ]);
        } catch (Exception) {
            return $this->autoRespond( 400, '出现异常错误，请您重试');
        }
    }

    public function update(Request $request): Response
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('authenticated_agent');
        
        $codeStr = $request->input('code', '');
        
        if (config('api.app.demo') === true) {
            return $this->autoRespond(400, '演示站模式，禁止操作');
        }

        $arr = Code::query()->where('code', $codeStr)->where('agent_id', $agent->id)->first();
        if (! $arr) {
            return $this->autoRespond(400, '查询不到此数据');
        }

        if ($request->filled('status')) {
            $arr->status = $request->input('status');
        }
        if ($request->filled('type')) {
            $gate = $this->assertTypeChangeAllowed($request->input('type'));
            if ($gate !== null) {
                return $gate;
            }
            $arr->type = $request->input('type');
        }
        if ($request->has('remark')) {
            $arr->remark = $request->input('remark');
        }
        if ($request->has('after_sale_day')) {
            $arr->after_sale_day = $request->input('after_sale_day', 0);
        }
        if ($request->has('after_sale_num')) {
            $arr->after_sale_num = $request->input('after_sale_num', 0);
        }
        if ($request->has('use_after_sale')) {
            $arr->use_after_sale = $request->input('use_after_sale', 0);
        }
        if ($request->has('verified_at')) {
            $arr->verified_at = $request->input('verified_at');
        }
        if ($request->has('maturity_at')) {
            $arr->maturity_at = $request->input('maturity_at');
        }

        if ($arr->save()) {
            return $this->autoRespond(200, '操作成功', $this->mapCodeItem($arr->fresh()));
        }
        return $this->autoRespond(400, '操作失败');
    }

    public function destroy(Request $request): Response
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('authenticated_agent');
        
        $codeStr = $request->input('code', '');
        
        $query = Code::query()->where('code', $codeStr)->where('agent_id', $agent->id);
        if (! $query->first()) {
            return $this->autoRespond(400, '不存在此数据');
        }

        try {
            $query->delete();
            return $this->autoRespond(200, '操作成功');
        } catch (Exception) {
            return $this->autoRespond(400, '异常错误，请您重试');
        }
    }

    public function status(Request $request): Response
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('authenticated_agent');
        
        $codeStr = $request->input('code', '');
        
        $item = Code::query()->where('code', $codeStr)->where('agent_id', $agent->id)->first();
        if (! $item) {
            return $this->autoRespond(400, '查询不到此数据');
        }
        return $this->autoRespond(200, '获取成功', $this->mapCodeItem($item->fresh()));
    }

    private function assertTypeGenerationAllowed(string $type): ?Response
    {
        switch ($type) {
            case 'processing':
                if (config('api.basics.processing_code_generate') === 'off') {
                    return $this->autoRespond(400, '平台已关闭生成预约类型卡密兑换');
                }
                break;
            case 'good':
                if (config('api.basics.good_code_generate') === 'off') {
                    return $this->autoRespond(400, '平台已关闭生成秒出类型卡密兑换');
                }
                break;
            default:
                if (config('api.basics.default_code_generate') === 'off') {
                    return $this->autoRespond(400, '平台已关闭生成默认类型卡密兑换');
                }
                break;
        }

        return null;
    }

    private function assertTypeChangeAllowed(string $type): ?Response
    {
        switch ($type) {
            case 'processing':
                if (config('api.basics.processing_code_generate') === 'off') {
                    return $this->autoRespond(400, '平台已关闭更改为预约类型卡密');
                }
                break;
            case 'good':
                if (config('api.basics.good_code_generate') === 'off') {
                    return $this->autoRespond(400, '平台已关闭更改为秒出类型卡密');
                }
                break;
            default:
                if (config('api.basics.default_code_generate') === 'off') {
                    return $this->autoRespond(400, '平台已关闭更改为默认类型卡密');
                }
                break;
        }

        return null;
    }

    private function mapCodeItem($item): array
    {
        return [
            'id' => $item->id,
            'code' => $item->code,
            'udid' => $item->udid,
            'devices_id' => $item->devices_id ?? null,
            'remark' => $item->remark,
            'status' => $item->status,
            'status_label' => $item->status === 'DISABLED' ? '已激活' : '未激活',
            'type' => $item->type,
            'type_label' => match ($item->type) {
                'good' => '秒出证书',
                'processing' => '预约证书',
                default => '默认类型',
            },
            'product' => $item->product,
            'product_label' => ($item->product ?? 'DEFAULT') === 'IPAD' ? '仅限iPad' : '全部机型',
            'after_sale_day' => $item->after_sale_day,
            'use_after_sale' => $item->use_after_sale,
            'after_sale_num' => $item->after_sale_num,
            'verified_at' => empty($item->verified_at) ? '' : $item->verified_at->format('Y-m-d H:i:s'),
            'maturity_at' => empty($item->maturity_at) ? '' : $item->maturity_at->format('Y-m-d H:i:s'),
            'created_at' => empty($item->created_at) ? '' : $item->created_at->format('Y-m-d H:i:s'),
            'updated_at' => empty($item->updated_at) ? '' : $item->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 根据请求方式返回响应
     * @param int $code
     * @param string $message
     * @param array $data
     * @return Response
     */
    private function autoRespond(int $code, string $message, array $data = []): Response
    {
        if (request()->isMethod('get')) {
            abort($code, $message);
        }
        $response['code'] = $code;
        $response['status'] = ($response['code'] >= 200 && $response['code'] < 300);
        $response['message'] = $message;
        $response['data'] = $data;
        return response()->json($response, $code);
    }
}