<?php

namespace kip\controllers;

use common\components\helpers\Y;
use common\components\notifications\NotificationManager;
use common\models\CbiasOrgs;
use common\models\CuratorOrg;
use common\models\Department;
use common\models\IssueComment;
use common\models\IssueFile;
use common\models\IssueResponse;
use common\models\IssueViewers;
use common\models\Log;
use common\models\Request;
use common\models\UserDepartment;
use kip\models\FilesUploadForm;
use Yii;
use common\models\Issue;
use kip\models\IssueSearch;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;
use common\models\Theme;
use common\models\User;

/**
 * IssueController implements the CRUD actions for Issue model.
 */
class IssueController extends Controller {
    /**
     * {@inheritdoc}
     */
    public function behaviors() {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Issue models.
     * @return mixed
     */
    public function actionIndex() {
        $searchModel = new IssueSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    //    public function actionUser() {
    //        $searchModel = new IssueSearch();
    //        $dataProvider = $searchModel->searchMy(Yii::$app->request->queryParams);
    //        return $this->render('user', [
    //            'searchModel' => $searchModel,
    //            'dataProvider' => $dataProvider,
    //        ]);
    //    }

    /**
     * Displays a single Issue model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id) {
        $model = $this->findModel($id);
        $issueComment = new IssueComment();
        $issueResponse = $model->issueResponse ?? new IssueResponse();
        $issueResponseFilesUpload = new FilesUploadForm();

        if ($issueComment->load(Yii::$app->request->post())) {
            if ($issueComment->save()) {
                NotificationManager::send($issueComment->user->isMinobr ? $model->user : $model->department->users, "Запрошено уточнение по судебной претензии №{$model->id}.", "(перейти)", ["url" => "/issue/view/{$model->id}",
                    "issue_id" => $model->id,
                ]);
                return $this->redirect(['view', 'id' => $model->id]);
            }
        } elseif ($issueResponse->load(Yii::$app->request->post()) && $issueResponseFilesUpload->load(Yii::$app->request->post())) {
            if ($issueResponse->save()) {
                $issueResponseFilesUpload->files = UploadedFile::getInstances($issueResponseFilesUpload, 'files');
                if ($responseFilesUploaded = $issueResponseFilesUpload->upload()) {
                    foreach ($responseFilesUploaded as $file) {
                        (new IssueFile([
                            'file_id' => $file->id,
                            'issue_id' => $model->id,
                        ]))->save();
                    }
                }
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('view', [
            'model' => $model,
            'issueComment' => $issueComment,
            'issueResponse' => $issueResponse,
            'issueResponseFilesUpload' => $issueResponseFilesUpload,
        ]);
    }

    public function actionClose($id) {
        $model = $this->findModel($id);
        $model->is_closed = 1;
        $model->save();
        $this->redirect('/issue/');
    }


    public function actionCreate() {
        $model = new Issue();
        $filesUploadForm = new FilesUploadForm();
        $deps = ArrayHelper::map(Department::getOnlyDeps(), 'id', 'name');

        if ($model->load(Yii::$app->request->post()) && $filesUploadForm->load(Yii::$app->request->post()) && UploadedFile::getInstances($filesUploadForm, 'files')) {
            if ($model->sum > $model->gz_sum * 0.05) {
                $model->department_id = Issue::DEPARTMENT_PRAV_ID;
            } else {
                $model->department_id = Issue::DEPARTMENT_CURATOR_ID;
            }


            //$model->department_id = ($model->sum > $model->gz_sum*0.05) ? Issue::DEPARTMENT_PRAV_ID : Issue::DEPARTMENT_CURATOR_ID; //$model->department_id;

            $inn = Department::find()->where([
                'id' => UserDepartment::find()->where(['user_id' => Yii::$app->user->id])->one()->department_id,
            ])->one()->inn;
            $idrubnubp = CbiasOrgs::find()->where(['inn' => $inn, 'fk_level' => 'Головное'])->one()->id_rubpnubp;
            $query = "select round(sum(total),2) as total, substring(blocksubbo_codesub from 6 for 8) as codesub
                        from fk_form_0503737_r1
                        where blocktypereport_id = (select max(blocktypereport_id) from fk_form_0503737_r1 where blocksubbo_codesub similar to '%({$idrubnubp})%' and to_date(period, 'DDMMYYYY') <= CURRENT_DATE 
                                group by to_date(period, 'DDMMYYYY') order by to_date(period, 'DDMMYYYY') desc limit 1)  
                           and period like to_char((select max(to_date(period, 'DDMMYYYY')) 
                        from fk_form_0503737_r1 
                        where to_date(period, 'DDMMYYYY') <= CURRENT_DATE 
                               and blocksubbo_codesub similar to '%({$idrubnubp})%'), 'DDMMYYYY')
                           and blockactionkind_id in (4)
                           and strcode in ('010')
                           and blocksubbo_codesub similar to '%({$idrubnubp})%'
                        group by period, substring(blocksubbo_codesub from 6 for 8)";
            $gz_sum = Yii::$app->ext_db->createCommand($query)->queryOne();
            $gz_sum = $gz_sum['total'];
            $model->gz_sum = $gz_sum;
            if ($model->sum > $model->gz_sum * 0.05) {
                $model->resp_user_id = 306;
            }

            if ($model->save()) {
                $files = UploadedFile::getInstances($filesUploadForm, 'files');
                $filesUploadForm->files = $files;
                if ($uploadedFiles = $filesUploadForm->upload()) {
                    foreach ($uploadedFiles as $file) {
                        (new IssueFile([
                            'issue_id' => $model->id,
                            'file_id' => $file->id,
                        ]))->save();
                    }
                } else {
                    return $this->redirect(['view', 'id' => $model->id]);
                }

                $curators = [];
                $curatorOrgs = CuratorOrg::findAll(['department_id'=> Y::user()->departments[0]->id]);
                foreach ($curatorOrgs as $curatorOrg) {
                    $curators[] = $curatorOrg->user;
                }

                @NotificationManager::send(
                    array_merge([User::convert($model->department->boss)], $curators),
                    "Новая судебная претензия.",
                    "(перейти)",
                    [
                        "url" => "/issue/view/$model->id",
                        "issue_id" => $model->id,]
                );


                if ($model->sum > $model->gz_sum * 0.05) {
                    $depIdList = [Issue::DEPARTMENT_DEP_ID, Issue::DEPARTMENT_CURATOR_ID];
                } else {
                    $depIdList = [Issue::DEPARTMENT_DEP_ID];
                }

                foreach ($depIdList as $depID) {
                    (new IssueViewers([
                        'issue_id' => $model->id,
                        'department_id' => $depID,
                    ]))->save();
                }

                return $this->redirect(['view', 'id' => $model->id]);
            }
        }


        if (Yii::$app->request->post() && !UploadedFile::getInstances($filesUploadForm, 'files'))
            Yii::$app->session->setFlash('error', "Прикрепите файлы, пожалуйста!");


        return $this->render('create', [
            'model' => $model,
            'filesUploadForm' => $filesUploadForm,
            'deps' => $deps,
        ]);
    }

    public function actionUpdate($id) {
        $model = $this->findModel($id);

        $filesUploadForm = new FilesUploadForm();

        $deps = ArrayHelper::map(Department::find()->all(), 'id', 'name');

        if ($model->load(Yii::$app->request->post()) && $filesUploadForm->load(Yii::$app->request->post()) && $model->save()) {
            $filesUploadForm->files = UploadedFile::getInstances($filesUploadForm, 'files');
            if ($uploadedFiles = $filesUploadForm->upload()) {
                foreach ($uploadedFiles as $file) {
                    (new IssueFile([
                        'issue_id' => $model->id,
                        'file_id' => $file->id,
                    ]))->save();
                }
            }
            return $this->redirect(['view', 'id' => $model->id]);
        }


        return $this->render('update', [
            'model' => $model,
            'filesUploadForm' => $filesUploadForm,
            'deps' => $deps,

        ]);
    }


    public function actionDelete($id) {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }


    protected function findModel($id) {
        if (($model = Issue::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionRedirect(int $id, int $department_id) {
        $issue = Issue::findOne($id);
        $issue->department_id = $department_id;
        $issue->resp_user_id = null;
        $issue->save();

        @NotificationManager::send($issue->department->boss, "Новое судебное обращение.", "(перейти)", ["url" => "/issue/view/{$issue->id}",
            "issue_id" => $issue->id,
        ]);

        Yii::$app->session->setFlash('success', "Претензия успешно перенаправлена.");
        return $this->redirect("/issue/");
    }

    public function actionDelegate($issue_id, $user_id, $code = "delegate") {
        Log::msg(["issue/actionDelegate", $issue_id, $user_id, $code]);
        $issue = Issue::findOne(['id' => $issue_id]);
        $user = User::findOne($user_id);
        $issue->resp_user_id = $user_id;
        if (!$issue->save()) {
            Yii::$app->session->setFlash('error', "К сожалению, произошла ошибка изменения судебного обращения.");
            return $this->redirect("/issue/view/$issue_id");
        }
        switch ($code) {
            case "delegate":
                NotificationManager::send($user, "Вы были назначены отвественным за судебное обращение.", "(перейти)", ["url" => "/issue/view/{$issue_id}",
                    "issue_id" => $issue_id,
                ]);
                $issue->department_id = $user->userDepartments[0]->department_id;
                $issue->save();
                Yii::$app->session->setFlash('success', "Судебное обращение успешно делегировано.");
                break;
            case "accept":
                Yii::$app->session->setFlash('success', "Вы приняли судебное обращение.");
                return $this->redirect("/issue/view/" . $issue_id);
        }
        return $this->redirect("/");
    }
}
