<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Code;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatisticsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('authenticated_agent');
        $agentId = $agent->id;

        $codeQuery = Code::query()->where('agent_id', $agentId);
        $today = Carbon::today('Asia/Shanghai');

        $todayActivations = Code::query()
            ->where('agent_id', $agentId)
            ->whereDate('verified_at', $today)
            ->orderByDesc('verified_at')
            ->get()
            ->map(fn ($item) => $this->mapCodeItem($item))
            ->values()
            ->all();

        $data = [
            'credit' => $agent->credit,
            'price' => $agent->price,
            'good_price' => $agent->good_price,
            'processing_price' => $agent->processing_price,
            'ipad_price' => $agent->ipad_price,
            'ipad_good_price' => $agent->ipad_good_price,
            'ipad_processing_price' => $agent->ipad_processing_price,
            'code_total' => (clone $codeQuery)->count(),
            'code_not_activated' => (clone $codeQuery)->where('status', 'ENABLED')->count(),
            'code_activated' => (clone $codeQuery)->where('status', 'DISABLED')->count(),
            'today_activated_codes' => $todayActivations,
        ];
        return $this->autoRespond(200, '获取成功', $data);
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
            'type_label' => $this->typeLabel($item->type),
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

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'good' => '秒出证书',
            'processing' => '预约证书',
            default => '默认类型',
        };
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