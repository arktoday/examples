<?php


namespace kip\models;


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

class FilesUploadForm extends Model {
    /**
     * @var UploadedFile[]
     */
    public $files;
    /**
     * @var UploadedFile[]
     */
    public $wrongFiles;

    public function rules() {
        return [
            [['files'], 'file', 'skipOnEmpty' => false, 'maxFiles' => 999, 'maxSize' => 99999999],
        ];
    }


    /**
     * @return File[]|false
     */
    public function upload() {
        if (!$this->validate())
            return false;
        /** @var File[] $uploadedFiles */
        $uploadedFiles = [];
        if(!is_array($this->files)){ //5 сек все
            $this->files = [$this->files];
        }
        foreach ($this->files as $file) {
            $storage = StorageHelper::getStorage();
            if ($storage->isAllowed($file->extension) && $path = $storage->processUploadedFileYii($file)) {
                $fileDB = new File(['path' => $path, "name" => $file->name]);
                if (!$fileDB->save()) {
                    Yii::$app->session->setFlash('error', "Файл $file->name не был загружен");
                    \Yii::error("Файлы не были загружены" . implode(" ", $fileDB->errors));
                }
                $uploadedFiles[] = $fileDB;
            }else{
                $this->wrongFiles[] = $file;
            }
        }
        return count($uploadedFiles) > 0 ? $uploadedFiles : false;
    }
}