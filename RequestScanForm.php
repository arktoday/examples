<?php


namespace kip\models;


use common\components\helpers\StorageHelper;
use common\models\File;
use x000000\StorageManager\Storage;
use yii\base\BaseObject;
use yii\web\UploadedFile;

class RequestScanForm extends \yii\base\Model {

    /**
     * @var UploadedFile
     */
    public $file;
    public $request_id;

    public function rules() {
        return [
            [['file'], 'file', 'skipOnEmpty' => true, 'maxSize' => 99999999],
        ];
    }
    public function attributeLabels()
    {
        return [
            'file' => 'Скан',
        ];
    }
    public function upload() {

        if ($this->validate()) {

            $files = [$this->file];

            foreach ($files as $file) {
                if(!$file) continue;
                $storage = StorageHelper::getStorage();
                if ($storage->isAllowed($file->extension) && $path = $storage->processUploadedFileYii($file)) {
                    $fileDB = new File(['path' => $path, "name" => $file->name]);
                    if (!$fileDB->save()) {
                        var_dump($fileDB->errors);
                        die;
                    }
                }
                else {
                    return false;
                }
            }
            return $fileDB??null;
        }

        return false;
    }
}