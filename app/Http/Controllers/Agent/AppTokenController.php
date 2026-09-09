<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AppTokenController extends Controller
{
    public function index(): Response
    {
        if (!Auth::guard('agent')->check()) {
            return redirect('/agent/login');
        }
        $agent = Auth::guard('agent')->user();

        return response()->view('agent.app_token', [
            'agent' => $agent->toArray(),
            'has_api_secret' => !empty($agent->api_secret),
            'token_masked' => $this->maskToken((string) $agent->token),
        ]);
    }

    public function generate(): Response
    {
        if (!Auth::guard('agent')->check()) {
            return response()->json([
                'code' => 400,
                'status' => false,
                'message' => '尚未登录或者会话超时',
                'data' => [],
            ], 400);
        }

        if (config('api.app.demo') === true) {
            return response()->json([
                'code' => 400,
                'status' => false,
                'message' => __('演示站模式，禁止操作'),
                'data' => [],
            ], 400);
        }

        $agent = Auth::guard('agent')->user();
        $plainKey = str()->password(48, symbols: false);

        $agent->token = (string) str()->uuid();
        $agent->api_secret = Hash::make($plainKey);
        $agent->save();

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => '已生成新的 Token 与高级密钥，请妥善保存（高级密钥仅显示一次）',
            'data' => [
                'email' => $agent->email,
                'token' => $agent->token,
                'key' => $plainKey,
            ],
        ], 200);
    }

    public function regenerateKey(): Response
    {
        if (!Auth::guard('agent')->check()) {
            return response()->json([
                'code' => 400,
                'status' => false,
                'message' => '尚未登录或者会话超时',
                'data' => [],
            ], 400);
        }

        if (config('api.app.demo') === true) {
            return response()->json([
                'code' => 400,
                'status' => false,
                'message' => __('演示站模式，禁止操作'),
                'data' => [],
            ], 400);
        }

        $agent = Auth::guard('agent')->user();
        $plainKey = str()->password(48, symbols: false);

        $agent->api_secret = Hash::make($plainKey);
        $agent->save();

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => '已生成新的高级密钥，请妥善保存（仅显示一次）',
            'data' => [
                'email' => $agent->email,
                'token' => $agent->token,
                'key' => $plainKey,
            ],
        ], 200);
    }

    private function maskToken(string $token): string
    {
        $len = strlen($token);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($token, 0, 4).str_repeat('*', $len - 8).substr($token, -4);
    }
}
