<?php

namespace App\Support\Traits;

use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as FoundationResponse;

trait ApiResponse
{
    /**
     * @var int
     */
    protected int $statusCode = FoundationResponse::HTTP_OK;

    protected array $header = [];

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @param $header
     *
     * @return $this
     */
    public function setHeader($header): static
    {
        $this->header = $header;

        return $this;
    }

    /**
     * @param $statusCode
     *
     * @return $this
     */
    public function setStatusCode($statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * @param $data
     * @param array $header
     *
     * @return \Illuminate\Http\JsonResponse
     */
    private function respond($data, $header = []): \Illuminate\Http\JsonResponse
    {
        return Response::json($data, $this->getStatusCode(), array_merge($header, $this->header));
    }

    /**
     * @param $status
     * @param array $data
     * @param null $code
     *
     * @return \Illuminate\Http\JsonResponse
     */
    private function status($status, array $data, $code = null): \Illuminate\Http\JsonResponse
    {
        if ($code) {
            $this->setStatusCode($code);
        }

        $status = [
            // 'status' => $status,
            'code' => $this->statusCode,
        ];
        // $data['debug']['data'] = $_SERVER['HTTP_ORIGIN'] ?? '';

        $data = array_merge($status, $data);

        return $this->respond($data);
    }

    /**
     * @param $data
     * @param string $status
     *
     * @return mixed
     */
    public function success($data = '操作成功', $status = "1")
    {
        $var_name = 'data';
        if (is_string($data)) {
            //如果data是字符串，则返回message格式信息
            $message = $data;
            $var_name = 'message';
        }

        return $this->status($status, compact($var_name));
    }

    /**
     * @param $message
     * @param int $code
     * @param string $status
     *
     * @return mixed
     */
    public function failed($message, $code = FoundationResponse::HTTP_BAD_REQUEST, $status = '0')
    {
        return $this->setStatusCode($code)->message($message, $status);
    }

    /**
     * @param $message
     * @param string $status
     *
     * @return mixed
     */
    public function message($message, $status = "success")
    {
        return $this->status($status, [
            'message' => $message,
        ]);
    }

    /**
     * @param string $message
     *
     * @return mixed
     */
    public function internalError($message = "Internal Error!")
    {
        return $this->failed($message, FoundationResponse::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param string $message
     *
     * @return mixed
     */
    public function created($message = "created")
    {
        return $this->setStatusCode(FoundationResponse::HTTP_OK)
            ->message($message);
    }

    /**
     * @param string $message
     *
     * @return mixed
     */
    public function notFond($message = 'Not Fond!')
    {
        return $this->failed($message, Foundationresponse::HTTP_NOT_FOUND);
    }
}
