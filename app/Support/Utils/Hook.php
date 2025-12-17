<?php

namespace App\Support\Utils;

/**
 *
 * Class Hook
 *
 * @package App\Support\Utils
 */
class Hook
{
    /**
     * 类名
     *
     * @var string
     */
    protected $namespace;

    /**
     * 方法前缀
     *
     * @var string
     */
    protected $prefix;

    /**
     * 构造方法
     * Hook constructor.
     *
     * @param string $namespace
     * @param string|null $prefix
     */
    public function __construct(string $namespace, string $prefix = null)
    {
        $this->namespace = $namespace;
        if ($prefix) {
            $this->prefix = $prefix;
        }
    }

    /**
     * 执行挂载方法
     *
     * @param string $hookName
     * @param mixed ...$arguments
     *
     * @return bool
     */
    public function listen(string $hookName, ...$arguments)
    {
        if (class_exists($this->namespace)) {
            $handle = app($this->namespace);
            $hookName = \Illuminate\Support\Str::studly(($this->prefix ?: '') . ucfirst($hookName));
            if (method_exists($handle, $hookName)) {
                try {
                    return call_user_func_array([$handle, $hookName], $arguments);
                } catch (\Throwable $e) {
                }
            }
        }

        return false;
    }
}
