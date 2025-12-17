<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller as BaseController;

class Controller extends BaseController
{
    protected $service;

    /**
     * @param null $data
     * @param string $msg
     * @param int $status
     *
     */
    protected function success($data = null, $msg = "success", $status = 200): JsonResponse
    {
        if (is_string($data)) {
            $msg = $data;
            $data = null;
        }

        if (is_numeric($data)) {
            $msg = trans('admin.' . $data);
            $data = null;
        }

        return response()->json(['status' => $status, 'msg' => $msg, 'data' => $data], 200, [], JSON_UNESCAPED_UNICODE);
    }

    protected function fail($msg, $data = null, $status = 400): JsonResponse
    {
        if (is_numeric($msg)) {
            $msg = trans('admin.' . $msg);
        }

        return response()->json(['status' => $status, 'msg' => $msg, 'data' => $data], 200, [], JSON_UNESCAPED_UNICODE);
    }

    protected function getMore(array $keys, bool $suffix = false): array
    {
        $args = [];
        $request = request();

        foreach ($keys as $key) {
            if (is_array($key)) {
                $field = is_array($key[0]) ? $key[0][0] : $key[0];
                $default = $key[1] ?? '';
                $args[$field] = $request->get($field) ?? $default;
            } else {
                $args[$key] = $request->get($key) ?? '';
            }
        }

        if ($suffix == true) {
            $args = array_values($args);
        }

        return $args;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateWithScene(array $data, string $validateClass, string $scene = ''): array
    {
        $validator = new $validateClass;
        $rules = [];
        $messages = [];

        if (is_object($validator)) {
            if (!empty($scene)) {
                $scenes = $validator->scenes()[$scene] ?? [];
            }

            $rules = $validator->rules();
            if (!empty($scenes) && !empty($rules)) {
                foreach ($rules as $key => $val) {
                    if (!array_key_exists($key, $scenes)) {
                        unset($rules[$key]);
                    }
                }
            }

            $messages = $validator->messages();
        }

        if (empty($rules)) {
            return [];
        }

        return $this->getValidationFactory()->make(
            $data, $rules, $messages, []
        )->validate();
    }
}
