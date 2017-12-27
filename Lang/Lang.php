<?php

/**
 * 语言包
 */
namespace Kernel\Lang;

class Lang
{
    /**
     * 语言包的源
     * @var string
     */
    public $sourceLanguage = "";


    /**
     * 翻译文案
     *
     * @var array
     */
    private $messages = [];


    /**
     * 初始化函数
     * @return
     */
    public function init()
    {
        $sourcePath = $this->sourceLanguage;
        if (! is_readable($sourcePath)) {
            return;
        }

        $iterator = new \RecursiveDirectoryIterator($sourcePath);
        $files = new \RecursiveIteratorIterator($iterator);
        foreach ($files as $file) {
            // 只监控php文件
            if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                continue;
            }
            $messages = str_replace([$sourcePath, '.php'], ["", ""], $file);
            list($language, $category) = explode("/", $messages);
            $this->messages[$language][$category] = require_once $file;
        }
    }



    /**
     * 翻译文本
     *
     * @param string $category 分类
     * @param array  $params   参数
     * @param string $language 语言
     *
     * @return string
     */
    public function translate(string $category, array $params, string $language)
    {
        $key = $category;
        $categoryFile = 'default';
        if (strpos($category, '.')) {
            list($categoryFile, $key) = explode(".", $category);
        }
        if (!isset($this->messages[$language][$categoryFile][$key])) {
            throw new \InvalidArgumentException("i18n翻译出错，category=" . $category . " 不存在！language=".$language);
        }
        $message = $this->messages[$language][$categoryFile][$key];
        return $this->formateMessage($message, $params);
    }


    /**
     * 格式化消息
     *
     * @param string $message 消息体
     * @param array  $params  参数
     *
     * @return string
     */
    private function formateMessage(string $message, array $params)
    {
        array_unshift($params, $message);
        return sprintf(...$params);
    }
}
