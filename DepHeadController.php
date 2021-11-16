<?php

namespace kip\controllers;

use common\components\DefaultDenyController;
use common\components\helpers\Y;
use common\models\Request;
use kip\models\RequestSearch;
use Yii;
use common\models\Department;
use admin\models\DepartmentSearch;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * DepHeadController implements the CRUD actions for Department model.
 */
class DepHeadController extends Controller {
    public function behaviors() {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],

            ],
        ]);
    }

    /**
     * Lists all Department models.
     * @return mixed
     */
    public function actionIndex($depId = null) {
        $searchModel = new DepartmentSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        //замы
        if ($depId) {
            $deps = Department::getQuery()->where(['parent_id' => $depId])->all();
        } else {
            $deps = Department::getQuery()->where([
                'parent_id' => Department::findOne(['boss_id' => \Yii::$app->user->id])->id
            ])->all();
        }

        $arZam = [];

        /** @var Department $item */
        foreach ($deps as $item) {
            $arZam[$item->id] = $item->id;
        }

        //отделы
        $otds = Department::getQuery()->where(['in', 'parent_id', $arZam])->all();

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'deps' => $deps,
        ]);
    }

    /**
     * Displays a single Department model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($otdId, $status) {
        $searchModel = new RequestSearch();
        $dataProvider = $searchModel->searchByOtdId(Yii::$app->request->queryParams, $otdId, $status);

        $sum = [
            "new" => 0,
            "curator" => 0,
            "in_progress" => 0,
            "overdue" => 0,
            "done" => 0,
        ];
        $sum["new"] += Request::find()->where(['request_status_id' => 1, 'sub_department_id' => $otdId])->count();
        $sum["curator"] += Request::find()->where(['request_status_id' => 2, 'sub_department_id' => $otdId])->count();
        $sum["in_progress"] += Request::find()->where(['request_status_id' => 3, 'sub_department_id' => $otdId])->count();
        $sum["overdue"] += Request::find()->where(['request_status_id' => 4, 'sub_department_id' => $otdId])->count();
        $sum["done"] += Request::find()->where(['request_status_id' => 5, 'sub_department_id' => $otdId])->count();


        return $this->render('view', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'otdId' => $otdId,
            'sum' => $sum,
        ]);
    }

    /**
     * Finds the Department model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Department the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id) {
        if (($model = Department::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
