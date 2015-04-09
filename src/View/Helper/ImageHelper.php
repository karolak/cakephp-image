<?php

namespace Image\View\Helper;

use Cake\ORM\TableRegistry;
use Cake\View\Helper;

/**
 * ImageHelper.
 *
 * @package Image\View\Helper
 * @author Krzysztof Karolak
 * @copyright 2015
 * @version 1.0
 */
class ImageHelper extends Helper
{
    /**
     * Used helpers.
     *
     * @var array
     */
    public $helpers = ['Html'];

    /**
     * Cached model paths.
     *
     * @var array
     */
    protected $modelPaths = [];

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
        /**
         * Base path where all images are.
         */
        'basePath' => null
    ];

    /**
     * Render image tag.
     *
     * @param $image
     * @param array $options
     * @return null
     */
    public function render($image, array $options = [])
    {
        if (empty($image['filename'])) {
            return null;
        }

        $preset = null;
        if (isset($options['preset'])) {
            $preset = $options['preset'];
            unset($options['preset']);
        }

        $url = $this->url($image, $preset);

        return $this->Html->image($url, $options);
    }

    /**
     * Get image url.
     *
     * @param $image
     * @param null $preset
     * @return string
     */
    public function url($image, $preset = null)
    {
        $path = $this->basePath($image);

        if (!empty($preset)) {
            $path .= $preset . DS;
        }

        return $path . $image['filename'];
    }

    /**
     * Get image base path for model.
     *
     * @param $image
     * @return string
     */
    public function basePath($image)
    {
        if (isset($this->modelPaths[$image['model']])) {
            return $this->modelPaths[$image['model']];
        }

        $basePath = $this->config('basePath');
        $table = TableRegistry::get($image['model']);
        if ($table->hasBehavior('Image')) {
            $basePath = $table->behaviors()->Image->config('path');
            $basePath = str_replace(WWW_ROOT, '/', $basePath);
        }

        return $this->modelPaths[$image['model']] = $basePath . DS . $image['model'] . DS;
    }
}