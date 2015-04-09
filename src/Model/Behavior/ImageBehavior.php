<?php

namespace Image\Model\Behavior;

use Cake\Event\Event;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\I18n\Time;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use WideImage\WideImage;

/**
 * ImageBehavior.
 *
 * @package Image\Model\Behavior
 * @author Krzysztof Karolak
 * @copyright 2015
 * @version 1.0
 */
class ImageBehavior extends Behavior
{
    /**
     * Images table.
     *
     * @var Table
     */
    protected $_imagesTable;

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
        /**
         * Table name used to save image data.
         */
        'table' => 'images',
        /**
         * Base path where to put all model images.
         */
        'path' => null,
        /**
         * Array of fields as 'field type' => 'cake association type'.
         */
        'fields' => [],
        /**
         * Other images to generate from source.
         * Image manipulation is handled by WideImage library.
         * Each array key is a directory name and values are arrays as 'operation' => 'params'.
         */
        'presets' => [],
        /**
         * Image quality for all presets.
         */
        'quality' => 100
    ];

    /**
     * Map field type used in config "fields" to cake association.
     *
     * @var array
     */
    protected $_associationMap = [
        'one' => 'hasOne',
        'many' => 'hasMany'
    ];

    /**
     * Behavior initialize method.
     *
     * @param array $config Config array.
     * @return void
     */
    public function initialize(array $config)
    {
        $this->_imagesTable = TableRegistry::get($this->config('table'));
        $this->_imagesTable->eventManager()->on('Model.afterDelete', [$this, 'imageAfterDelete']);
        $this->setupAssociations($this->config('table'), $this->config('fields'));
    }

    /**
     * Setup table associations.
     *
     * @param string $imagesTableName Table name for images.
     * @param array $fields Array as 'fieldName' => 'fieldType'.
     * @return void
     */
    protected function setupAssociations($imagesTableName, $fields)
    {
        $alias = $this->_table->alias();

        foreach ($fields as $fieldName => $fieldType) {
            $associationName = (isset($this->_associationMap[$fieldType])) ? $this->_associationMap[$fieldType] : $this->_associationMap['one'];
            $name = 'images_' . strtolower($alias) . $fieldName;
            $targetTable = TableRegistry::get($name);
            $targetTable->table($imagesTableName);

            $this->_table->{$associationName}($name, [
                'targetTable' => $targetTable,
                'foreignKey' => 'foreign_key',
                'joinType' => 'LEFT',
                'propertyName' => $fieldName,
                'conditions' => [
                    $name . '.model' => $alias,
                    $name . '.field' => $fieldName
                ]
            ]);
        }

        $this->_table->hasMany($imagesTableName, [
            'name' => $imagesTableName,
            'foreignKey' => 'foreign_key',
            'strategy' => 'select',
            'propertyName' => '_images',
            'dependent' => true,
            'cascadeCallbacks' => true,
            'conditions' => [
                $imagesTableName . '.model' => $alias
            ]
        ]);
    }

    /**
     * Implementation of the beforeSave event, handles uploading / saving and overwriting of image records.
     *
     * @param Event $event Event object.
     * @param Entity $entity Entity object.
     * @param \ArrayObject $options Options array.
     * @return void
     */
    public function beforeSave(Event $event, Entity $entity, \ArrayObject $options)
    {
        $fields = $this->config('fields');
        $alias = $this->_table->alias();

        $options['associated'] = [$this->_imagesTable->alias() => ['validate' => false]] + $options['associated'];
        $entities = [];

        foreach ($fields as $fieldName => $fieldType) {
            $uploadedImages = [];
            $field = $entity->get($fieldName);
            $field = $fieldType == 'one' ? [ $field ] : $field;

            foreach ($field as $image) {
                $result = array();
                if (!empty($image['tmp_name'])) {
                    $result = $this->_upload($image['name'], $image['tmp_name'], false);
                }
                elseif (is_string($image)) {
                    $result = $this->_upload($image, $image, true);
                }

                if (!empty($result)) {
                    $uploadedImages[] = $result + ['model' => $alias, 'field' => $fieldName];
                }
            }

            if (!empty($uploadedImages)) {
                if (!$entity->isNew() && $fieldType == 'one') {
                    $preexisting = $this->_imagesTable->find()
                        ->where(['model' => $alias, 'field' => $fieldName, 'foreign_key' => $entity->id])
                        ->bufferResults(false);

                    foreach ($preexisting as $index => $image) {
                        $this->_imagesTable->delete($image);
                    }
                }

                foreach ($uploadedImages as $image) {
                    $entities[] = $this->_imagesTable->newEntity($image);
                }
            }

            $entity->dirty($fieldName, true);
        }

        $entity->set('_images', $entities);
    }

    /**
     * Implementation of afterSave event, handles generating additional files.
     *
     * @param Event $event Event object.
     * @param Entity $entity Entity object.
     * @param \ArrayObject $options Options array.
     * @return void
     */
    public function afterSave(Event $event, Entity $entity, \ArrayObject $options)
    {
        $images = $entity->get('_images');
        if (!empty($images)) {
            foreach ($images as $imageEntity) {
                $this->generatePresets($imageEntity);
            }

            $entity->unsetProperty('_images');
        }
    }

    /**
     * Implementation of afterDelete event for images table, handles deleting image files.
     *
     * @param Event $event Event object.
     * @param Entity $entity Entity object.
     * @param \ArrayObject $options Options array.
     * @return void
     */
    public function imageAfterDelete(Event $event, Entity $entity, \ArrayObject $options) {
        $shared = $this->_imagesTable->find()->where([
                'foreign_key !=' => $entity->foreign_key,
                'model' => $entity->model,
                'filename' => $entity->filename
            ]);
        if (!$shared->count()) {
            $basePath = $this->basePath();
            (new File($basePath . DS . $entity->filename))->delete();
            foreach($this->config('presets') as $preset => $options) {
                (new File($basePath . DS . $preset .DS . $entity->filename))->delete();
            }
        }
    }

    /**
     * Upload function.
     *
     * @param string $fileName Original name of the tmp file.
     * @param string $filePath Full path to the tmp file.
     * @param bool $copy Whether copy or move the tmp file.
     * @return array
     */
    protected function _upload($fileName, $filePath, $copy = false)
    {
        $data = [];
        if (!file_exists($filePath)) {
            return $data;
        }

        $fileName = $this->generateUniqueFilename($fileName, $filePath);
        $basePath = $this->basePath();

        $fullPath = $basePath . DS . $fileName;
        $folder = new Folder($basePath, true, 0775);
        $transferFn = $copy ? 'copy' : 'move_uploaded_file';

        if (file_exists($fullPath) || call_user_func_array($transferFn, [ $filePath, $fullPath ])) {
            $file = new File($fullPath);
            if (false !== $file->size()) {
                $data = [
                    'filename' => $fileName,
                    'size' => $file->size(),
                    'mime' => $file->mime(),
                    'created' => Time::now()
                ];
            }
        }

        return $data;
    }

    /**
     * Generate source image presets.
     *
     * @param Entity $imageEntity Image entity.
     * @param bool $force Use force to override image if exists.
     * @return void
     */
    public function generatePresets($imageEntity, $force = false)
    {
        $basePath = $this->basePath($imageEntity->model) . DS;
        $imagePath = $basePath . $imageEntity->filename;
        $quality = $this->getCorrectQuality(pathinfo($imagePath, PATHINFO_EXTENSION));

        $presets = $this->config('presets');
        foreach ($presets as $preset => $options) {
            $dir = $basePath . $preset . DS;
            $folder = new Folder($dir, true, 0775);
            $destination = $dir . $imageEntity->filename;

            if (!$force && file_exists($destination)) {
                continue;
            }

            $wImage = WideImage::load($imagePath);
            foreach ($options as $action => $params) {
                if (is_callable($params)) {
                    $wImage = $params($wImage, $imagePath);
                }
                else {
                    $wImage = call_user_func_array([$wImage, $action], $params);
                }
            }

            $wImage->saveToFile($destination, $quality);
        }
    }

    /**
     * Generates unique filename.
     *
     * @param string $fileName Name of the file.
     * @param string $filePath File path.
     * @return string
     */
    public function generateUniqueFilename($fileName, $filePath)
    {
        return md5_file($filePath) . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
    }

    /**
     * Return base path for current model or override by 'alias' parameter.
     *
     * @param null|string $alias Optional name for model directory in base path.
     * @return string
     */
    public function basePath($alias = null)
    {
        if (!$alias) {
            $alias = $this->_table->alias();
        }

        return $this->config('path') . DS . $alias;
    }

    /**
     * Get correct quality to file extension.
     * Default is 0-100, but some files (like .png) needs 0-9 scale.
     *
     * @param string $extension File extension returned from pathinfo().
     * @return int
     */
    public function getCorrectQuality($extension)
    {
        $quality = $this->config('quality');
        if (strtolower($extension) == 'png') {
            $quality = 9 - round(($quality / 100) * 9);
        }

        return $quality;
    }

}
