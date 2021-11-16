<?php


namespace common\models;

use common\components\helpers\StorageHelper;
use common\models\File;
use common\models\RequestFile;
use common\models\TaskFile;
use x000000\StorageManager\Storage;
use Yii;
use yii\base\BaseObject;
use yii\base\Model;
use yii\db\Exception;
use yii\helpers\Json;
use yii\web\UploadedFile;

class FileUploadForm extends Model {
    /**
     * @var UploadedFile
     */
    public $file;
    /**
     * @var UploadedFile
     */
    public $wrongFile;

    public function rules() {
        return [
            [['file'], 'file', 'skipOnEmpty' => false, 'maxFiles' => 1, 'maxSize' => 1024 * 1024 * 51200],
        ];
    }


    /**
     * @return File|false
     */
    public function upload() {
        if (!$this->validate()) {
            Yii::error($this->errors);
            return false;
        }
        /** @var File $uploadedFile */
        $uploadedFile = false;
        $storage = StorageHelper::getStorage();
        $file = $this->file;
        Yii::debug($file, '234');
        if ($storage->isAllowed($file->extension) && $path = $storage->processUploadedFileYii($file)) {
            $fileDB = new File(['path' => $path, "name" => $file->name]);
            if (!$fileDB->save()) {
                Yii::$app->session->setFlash('error', "Файл $file->name не был загружен");
                \Yii::error("Файлы не были загружены" . implode(" ", $fileDB->errors));
            }
            $uploadedFile = $fileDB;
        } else {
            Yii::error('!$storage->isAllowed or !$storage->processUploadedFileYii');
            $this->wrongFile = $file;
        }

        return $uploadedFile ?? false;
    }
}