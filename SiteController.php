<?php

namespace kip\controllers;

use admin\models\SignupForm;
use common\components\DefaultDenyController;
use common\components\helpers\RBACHelper;
use common\components\helpers\Y;
use common\models\Department;
use common\models\File;
use common\models\IasEstateOrganizationFounder;
use common\models\IasEstateOrganizations;
use common\models\IasEstateOrganizationType;
use common\models\MeetingUser;
use common\models\OrgType;
use common\models\Project;
use common\models\Regionsvpo1;
use common\models\Request;
use common\models\Status;
use common\models\structure\Organization;
use common\models\User;
use common\models\UserActivation;
use common\models\UserDepartment;
use common\models\UserProject;
use common\models\WorkPosition;
use kip\models\FilesUploadForm;
use kip\models\PasswordResetRequestForm;
use kip\models\RequestSearch;
use kip\models\ResetPasswordForm;
use Matrix\Exception;
use phpDocumentor\Reflection\Location;
use Yii;
use yii\base\BaseObject;
use yii\base\InvalidArgumentException;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\models\LoginForm;

use common\models\db\QueryCreator;

use common\helper\db\ActiveRecord;

use common\models\test\DBSchemaUpdater;
use yii\web\Response;
use yii\web\UploadedFile;


/**
 * Site controller
 */
class SiteController extends Controller {
    /**
     * {@inheritdoc}
     */
    public function behaviors() {
        return [
            'verbs' => [
                'class'   => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions() {
        return [
            'error'   => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class'           => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }


    public function actionIndex() {
        $hasAccess = Y::can("access_" . str_replace(".", "_", $_SERVER['HTTP_HOST']));
        if(!$hasAccess) {
            return $this->redirect('/site/login');
        }

        $searchModel = new RequestSearch();
        $dataProvider = Y::user()->isMinobr
            ? $searchModel->searchByDepartmentId(\Yii::$app->request->queryParams, Y::user()->id, false)
            : $searchModel->search([]);

        $meetingUsers = ArrayHelper::map(
            MeetingUser::findAll(['user_id'=>Y::user()->id]), 'id', 'meeting_id'
        );

        @$links = [
            [
                'url' => substr(RbacHelper::getRedirectLinkBasedOnRole(Y::user()->id), 1),
                'title' => 'Обращения',
                'module_id' => 'request_id',
                'img_path' => '/img/index-handling.svg',
                'all_count' => $dataProvider->totalCount,
            ],
            [
                'url' => 'task/index',
                'title' => 'Задачи',
                'module_id' => 'task_id',
                'img_path' => '/img/index-task.svg',
                'all_count' => count(Y::user()->tasks),
            ],
            [
                'url' => 'issue/index',
                'title' => 'Судебный мониторинг',
                'module_id' => 'issue_id',
                'img_path' => '/img/index-trail.svg'
            ],
            [
                'url' => 'https://new.iacmon.ru/lk/main/',
                'permission' => 'lk/main',
                'title' => 'Совет ФХД',
                'module_id' => '',
                'img_path' => '/img/index-council.svg',
                'all_count' => count(Y::user()->projects),

            ],
//            [
//                'url' => 'project/index',
//                'title' => 'Проекты',
//                'module_id' => 'project_id',
//                'img_path' => '/img/index-project.svg',
//                'all_count' => count(Y::user()->projects),
//
//            ],
            [
                'url' => 'meeting-request',
                'permission' => 'meeting-request/index',
                'title' => 'Записи на прием',
                'module_id' => 'meeting_request_id',
                'img_path' => '/img/index-project.svg',
            ],
            [
                'url' => !!$meetingUsers ? 'meeting/list' : 'meeting/index',
                'permission' => !!$meetingUsers ?: 'meeting/index',
                'title' => 'Встречи',
                'module_id' => 'meeting_id',
                'img_path' => '/img/index-project.svg',
            ],
        ];

        return $this->render('index', ['links'=>$links]);
    }

    /**
     * Logs in a user.
     *
     * @return mixed
     *
     **/
    public function actionLogin() {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = 'no-header';

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }
        else {
            $model->password = '';

            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Logs out the current user.
     *
     * @return mixed
     */
    public function actionLogout() {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionSelectDep() {

        $deps = ArrayHelper::map(Department::find()->where(['not', ['is_minobr' => 1]])->all(), "id", "name");

        if (Yii::$app->request->post("deps")) {
            $depId = Yii::$app->request->post("deps");
            return $this->redirect(['newsignup',
                'depId' => $depId,
            ]);
        }

        return $this->render('select-dep', [
            'deps' => $deps,
        ]);
    }

    public function actionNewsignup($ref = null) {
        if ($ref) {
            $HTTP_REFERER = $ref;
        }

        if (Yii::$app->request->get("depId")) {

            $depId = Yii::$app->request->get("depId");
            $resp = self::checkOrg($depId);

            $model = new SignupForm();
            $workPosModel = new WorkPosition();
            $fileModel = new FilesUploadForm();
            $userActivation = new UserActivation();

            if ($model->load(Yii::$app->request->post())) {
                if ($workPosModel->load(Yii::$app->request->post())) {
                    if (WorkPosition::find()->where(['name' => $workPosModel->name])->count() == 0) {
                        $workPosModel->sort = 2;
                        $workPosModel->save();
                        $model->work_position = $workPosModel->name;
                    } else {
                        $model->work_position = $workPosModel->name;
                    }

                    if ($fileModel->load(Yii::$app->request->post())) {
                        $fileModel->files = UploadedFile::getInstances($fileModel, 'files');
                        /** @var File $profileFileUploaded */
                        if ($activationFileUploaded = $fileModel->upload()) {
                            $emailParts = explode("@", $model->email);
                            $username = $emailParts[0];
                            $count = User::find()->where(['username' => $username])->count();
                            $model->username = ($count > 0) ? $username . ++$count : $username;
                            $model->password = Yii::$app->security->generateRandomString(12);
                            if ($userId = $model->kipsignup()) {
                                if (isset($HTTP_REFERER)) {
                                    $user = User::find()->where(['id' => $userId])->one();
                                    $user->reffer_http = $HTTP_REFERER;
                                    $user->save();
                                }
                                $userDep = new UserDepartment();
                                $userDep->user_id = $userId;
                                $userDep->department_id = $depId;
                                if ($userDep->save()) {
                                    $userActivation->activation_photo_id = $activationFileUploaded[0]->id;
                                    $userActivation->user_id = $userId;
                                    Yii::info("new activation photo id = {$activationFileUploaded[0]->id}");
                                    if ($userActivation->save()) {
                                        Yii::$app->session->setFlash('success', "Ваши данные были отправлены на проверку. Проверьте электронную почту.");
                                        $user = User::find()->where(['id' => $userId])->one();
                                        if ($user->reffer_http) {
                                            return header('Location: http://' . $user->reffer_http);
                                        }
                                        return $this->redirect(['site/login']);
                                    }
                                } else {
                                    Yii::warning(['userDep saving failure' => $userDep]);
                                    Yii::$app->session->setFlash('danger', "Ошибка регистрации.");
                                }
                            } else {
                                Yii::$app->session->setFlash('danger', "Неудача регистрации пользователя");
                                Yii::warning(['user saving failure' => $model]);
                            }
                        } else {
                            Yii::$app->session->setFlash('danger', "Неудача загрузки изображения1");
                            Yii::warning(['wrong file' => $fileModel->wrongFiles]);
                        }
                    } else {
                        Yii::$app->session->setFlash('danger', "Неудача загрузки изображения");
                        Yii::warning(['wrong file' => $fileModel->wrongFiles]);
                    }
                }
            }

            return $this->render('newsignup', [
                'model' => $model,
                'workPosModel' => $workPosModel,
                'fileModel' => $fileModel,
                'depId' => $depId,
                'resp' => $resp
            ]);

        } else {
            Yii::$app->session->setFlash('warning', "Выберите организацию");
            return $this->redirect(['select-dep']);
        }
    }

    public function actionSignup($ref = null) {
        if ($ref) {
            $HTTP_REFERER = $ref;
        }

        $model = new SignupForm();
        $workPosModel = new WorkPosition();
        $fileModel = new FilesUploadForm();
        $userActivation = new UserActivation();
        $orgTypes = OrgType::find()->all();
        $orgTypes = ArrayHelper::map($orgTypes, 'id', 'name');

        if ($model->load(Yii::$app->request->post())) {
            if ($workPosModel->load(Yii::$app->request->post())) {
                if (WorkPosition::find()->where(['name' => $workPosModel->name])->count() == 0) {
                    $workPosModel->sort = 2;
                    $workPosModel->save();
                    $model->work_position = $workPosModel->name;
                }
                else {
                    $model->work_position = $workPosModel->name; /*WorkPosition::findOne(['name' => $workPosModel->name])->name;*/
                }
                if (Yii::$app->request->post('ogrn')) {
                    $ogrn = Yii::$app->request->post('ogrn');

                    if ($fileModel->load(Yii::$app->request->post())) {
                        $fileModel->files = UploadedFile::getInstances($fileModel, 'files');
                        /** @var File $profileFileUploaded */
                        if ($activationFileUploaded = $fileModel->upload()) {
//                            $emailParts = explode("@", $model->email);
//                            $username = $emailParts[0];
//                            $count = User::find()->where(['username' => $username])->count();
//                            $model->username = ($count > 0) ? $username . ++$count : $username;
                            $model->password = Yii::$app->security->generateRandomString(12);
                            if ($userId = $model->kipsignup()) {
                                if (isset($HTTP_REFERER)) {
                                    $user = User::find()->where(['id' => $userId])->one();
                                    $user->reffer_http = $HTTP_REFERER;
                                    $user->save();
                                }

                                $userDep = new UserDepartment();
                                $userDep->user_id = $userId;
                                try {

                                    $rows = Yii::$app->ext_db->createCommand("
                                    select id_fk from orgs_list.orgs as m, 
                                    fns.egrul_orgs as eg 
                                    where m.inn=eg.inn 
                                     and stop_reason_code is null 
                                     and id_fk is not null 
                                    and eg.ogrn = :ogrn", [':ogrn' => $ogrn])->queryOne();
//                                    echo json_encode($rows['id_fk']);

                                    $userDep->department_id = Department::find()->where([
                                        'org' => $rows['id_fk']])->one()->id;

                                } catch (\Exception $e) {
                                    User::deleteAll('id = :id', [':id' => $userId]);
                                    Yii::warning(['org not found' => $e]);
                                    Yii::$app->session->setFlash('danger', "Ошибка регистрации.1");
                                    return $this->redirect(['site/signup']);
                                }
                                if ($userDep->save()) {
                                    $userActivation->activation_photo_id = $activationFileUploaded[0]->id;
                                    $userActivation->user_id = $userId;
                                    Yii::info("new activation photo id = {$activationFileUploaded[0]->id}");
                                    if ($userActivation->save()) {
                                        Yii::$app->session->setFlash('success', "Ваши данные были отправлены на проверку. Проверьте электронную почту.");
                                        $user = User::find()->where(['id' => $userId])->one();
                                        if ($user->reffer_http) {
                                            return header('Location: http://' . $user->reffer_http);
                                        }
                                        return $this->redirect(['site/login']);
                                    }
                                } else {
                                    Yii::warning(['userDep saving failure' => $userDep]);
                                    Yii::$app->session->setFlash('danger', "Ошибка регистрации.");
                                    return $this->redirect(['site/signup']);
                                }
                            } else {
                                Yii::$app->session->setFlash('danger', "Неудача регистрации пользователя");
                                Yii::warning(['user saving failure' => $model]);
                            }
                        }
                        else {
                            Yii::$app->session->setFlash('danger', "Неудача загрузки изображения1");
                            Yii::warning(['wrong file' => $fileModel->wrongFiles]);
                        }
                    }
                    else {
                        Yii::$app->session->setFlash('danger', "Неудача загрузки изображения");
                        Yii::warning(['wrong file' => $fileModel->wrongFiles]);
                    }
                }
            }
        }

        return $this->render('signup', [
            'model'        => $model,
            'workPosModel' => $workPosModel,
            'fileModel'    => $fileModel,
            'orgTypes'     => $orgTypes,
        ]);
    }

    static function checkOrg($depId) {
        $resp = [];
        $org = Department::find()->where(['id' => $depId])->one()->org;
        if ($org) {
            $rows = Yii::$app->ext_db->createCommand("
                                    select m.ogrn from orgs_list.orgs as m, 
                                    fns.egrul_orgs as eg 
                                    where m.inn=eg.inn 
                                     and stop_reason_code is null 
                                     and m.ogrn is not null 
                                    and m.id_fk = :org", [':org' => $org])->queryOne();
//                                    echo json_encode($rows['id_fk']);
            $ogrn = $rows['ogrn'];

            $orgModel = IasEstateOrganizations::find()->where(['ogrn' => $ogrn])->one();
            $orgModel->fullname = str_replace('&#34;', '"', $orgModel->fullname);
            $resp = $orgModel->toArray();
            $resp['region'] = Regionsvpo1::find()->where(['ias_id' => $orgModel->id_region])->one()->name;
            $orgType = IasEstateOrganizationType::find()->where(['id' => $orgModel->id_type])->one();
            $resp['type'] = "($orgType->abbr) $orgType->value";
            $resp['founder'] = IasEstateOrganizationFounder::find()->where(['id' => $orgModel->id_founder])->one()->value;

            return $resp;
        }
    }

    public function actionCheckOgrn() {
        if (Yii::$app->request->isAjax) {
            $resp = [];
            $ogrn = Yii::$app->request->post('ogrn');
            $orgModel = IasEstateOrganizations::find()->where(['ogrn' => $ogrn])->one();
            $orgModel->fullname = str_replace('&#34;', '"', $orgModel->fullname);
            $resp = $orgModel->toArray();
            $resp['region'] = Regionsvpo1::find()->where(['ias_id' => $orgModel->id_region])->one()->name;
            $orgType = IasEstateOrganizationType::find()->where(['id' => $orgModel->id_type])->one();
            $resp['type'] = "($orgType->abbr) $orgType->value";
            $resp['founder'] = IasEstateOrganizationFounder::find()->where(['id' => $orgModel->id_founder])->one()->value;

            Yii::$app->response->format = Response::FORMAT_JSON;
            return $resp;
        }
    }

    public function actionRequestPasswordReset() {
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->session->setFlash('success', 'Проверьте свою электронную почту.');

                return $this->goHome();
            }

            Yii::$app->session->setFlash('error', 'К сожалению, мы не можем сбросить пароль для указанного адреса электронной почты.');
        }

        return $this->render('requestPasswordReset', [
            'model' => $model,
        ]);
    }

    public function actionResetPassword($token, $reffer_http = null) {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->session->setFlash('success', 'Новый пароль сохранен.');

            if ($reffer_http) {
                return header('Location: http://' . $reffer_http);
            }
            return $this->redirect(Yii::$app->urlManager->createUrl(['site/index']));
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }

    public function actionIndex_test() {
        $this->layout = 'main_new';
        $hasAccess = Y::can("access_" . str_replace(".", "_", $_SERVER['HTTP_HOST']));
        if(!$hasAccess) {
            return $this->redirect('/site/login');
        }
        return $this->render('index_test', []);
    }

    public function actionMaria($page_name) {
        return $this->render("/tests/$page_name", []);
    }

    public function actionTest_push() {
        $this->layout = 'main';
        return $this->render('/test/push', ["data" => []]);
    }

}
