<?php


namespace kip\models;


use common\components\helpers\StorageHelper;
use common\models\File;
use common\models\RequestFile;
use common\models\TaskFile;
use kartik\daterange\DateRangeBehavior;
use x000000\StorageManager\Storage;
use Yii;
use yii\base\BaseObject;
use yii\base\Model;
use yii\db\Exception;
use yii\helpers\Json;
use yii\web\UploadedFile;

class AnalystForm extends Model {
    public $date;
    public $createTimeRange;
    public $datetime_min;
    public $datetime_max;


    public function behaviors()
    {
        return [
            [
                'class' => DateRangeBehavior::className(),
                'attribute' => 'date',
                'dateStartAttribute' => 'datetime_min',
                'dateEndAttribute' => 'datetime_max',
            ]
        ];
    }

    public function rules() {
        return [
            [['date'], 'safe'],
            [['createTimeRange'], 'match', 'pattern' => '/^.+\s\-\s.+$/'],

        ];
    }

    public function attributeLabels() {
        return [
            'date' => 'Тип согласования',
        ];
    }

}