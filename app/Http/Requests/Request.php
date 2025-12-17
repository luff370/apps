<?php

namespace App\Http\Requests;

use App\Exceptions\RequestException;
use Illuminate\Foundation\Http\FormRequest;

abstract class Request extends FormRequest
{
    /**
     * 表示验证器是否应在第一个规则失败时停止。
     *
     * @var bool
     */
    protected $stopOnFirstFailure = true;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [];
    }

    public function scenes(): array
    {
        return [];
    }

    /**
     * Create the default validator instance.
     *
     * @param \Illuminate\Contracts\Validation\Factory $factory
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function createDefaultValidator(\Illuminate\Contracts\Validation\Factory $factory)
    {
        $validator = $factory->make(
            $this->validationData(), $this->getSceneRules(),
            $this->messages(), $this->attributes()
        )->stopOnFirstFailure($this->stopOnFirstFailure);

        if ($this->isPrecognitive()) {
            $validator->setRules(
                $this->filterPrecognitiveRules($validator->getRulesWithoutPlaceholders())
            );
        }

        return $validator;
    }

    /**
     * 获取场景验证规则
     *
     * @return array
     */
    protected function getSceneRules(): array
    {
        return $this->handleScene($this->container->call([$this, 'rules']));
    }

    /***
     * 基于路由名称的场景验证
     *
     * @param array $rule
     *
     * @return array
     */
    public function handleScene(array $rule): array
    {
        $arr = [];
        $scenes = $this->scenes();
        $sceneName = $this->getSceneName();
        if (empty($scenes) || empty($sceneName)) {
            return $rule;
        }

        foreach (($scenes[$sceneName] ?? []) as $item) {
            if (isset($rule[$item])) {
                $arr[$item] = $rule[$item];
            }
        }

        return $arr ?: $rule;
    }

    /**
     * 获取场景名称
     *
     * @return string|null
     */
    public function getSceneName(): string|null
    {
        return $this->input('_scene', $this->route()->getName());
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Validation\Validator $validator
     *
     * @throws \App\Exceptions\RequestException
     */
    protected function failedValidation($validator)
    {
        throw new RequestException($validator->errors()->first(), 422);
    }
}
