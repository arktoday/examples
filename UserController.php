<?php

namespace kip\controllers;

use admin\models\SignupForm;
use common\components\helpers\Y;
use common\models\Department;
use common\models\File;
use common\models\Theme;
use common\models\UserDepartment;
use common\models\UserTheme;
use common\models\WorkPosition;
use dosamigos\fileupload\FileUpload;
use common\models\FileUploadForm;
use kip\models\FilesUploadForm;
use kip\models\UserMinSearch;
use Yii;
use common\models\User;
use kip\models\UserSearch;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;


//some new code ...
/**
 * UserController implements the CRUD actions for User model.
 */
class UserController extends Controller {
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

    public function actionIndex() {
        $searchModel = new UserSearch();
        $dataProvider = $searchModel->searchByComp(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionMin($id) {
        $searchModel = new UserSearch();
        $dataProvider = $searchModel->searchMin(Yii::$app->request->queryParams, $id);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'department' => Department::findOne(['id' => $id]),
            'url' => '/request/min-head/',
        ]);
    }

    public function actionOrg($id = false) {
        if (!$id) $id = Y::user()->departments[0]->id;

        $searchModel = new UserSearch();
        $dataProvider = $searchModel->searchMin(Yii::$app->request->queryParams, $id);


        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'department' => Department::findOne(['id' => $id]),
            'url' => '/org-head/',
        ]);
    }


    public function actionView($id) {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionEdit($id) {
        $user = $this->findModel($id);
        $fileModel = new FilesUploadForm();
        if ($user->load(Yii::$app->request->post())) {
            if (!$user->date_delegated_due_range){
                $user->date_delegated_due_start = null;
                $user->date_delegated_due_end = null;
            }
//            var_dump($user->date_delegated_due_range);
//            die;
            $user->save();
            if ($fileModel->load(Yii::$app->request->post())) {
                $fileModel->files = UploadedFile::getInstances($fileModel, 'files');
                /** @var File $profileFileUploaded */
                if ($profileFileUploaded = $fileModel->upload()){
                    $user->profile_photo_id = $profileFileUploaded[0]->id;
                    Yii::info("new photo id = {$profileFileUploaded[0]->id}");
                    $user->save();
                }else {
                    Yii::warning(['wrong file' => $fileModel->wrongFiles]);
                }
            }
            return $this->redirect(['view', 'id' => $user->id]);
        }

        return $this->render('edit', [
            'model' => $user,
            'fileModel' => $fileModel,
            'showCertificate' => true //Y::user()->isMinobr,
        ]);
    }

    /**
     * Creates a new User model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate() {
        $model = new User();
        $workPosModel = new WorkPosition();
        $userDepartmentModel = new UserDepartment();
        $fileModel = new FilesUploadForm();

        if (Yii::$app->request->post()) {
            Yii::debug(Yii::$app->request->post(), '123');
            //  'User' => [
            //        'username' => 'user_test1',
            //        'first_name' => 'asdf',
            //        'second_name' => 'asdf',
            //        'last_name' => 'asdf',
            //        'email' => 'vicon45685@herrain.com',
            //    ],
        }


        if ($model->load(Yii::$app->request->post())) {
            Yii::debug(['afterload' => $model->toArray()], '123');
            //  'afterload' => [
            //        'first_name' => 'asdf',
            //        'second_name' => 'asdf',
            //        'last_name' => 'asdf',
            //    ],




            if ($workPosModel->load(Yii::$app->request->post())) {
                if (!WorkPosition::find()->where(['name' => $workPosModel->name])->count()) {
                    $workPosModel->sort = 2;
                    $workPosModel->save();
                    $model->work_position_id = $workPosModel->id;
                } else {
                    $model->work_position_id = WorkPosition::findOne(['name' => $workPosModel->name])->id;
                }

            } else {
                Yii::error(['$workPosModel'=>$workPosModel->errors], '123');
            }

            $model->setPassword(Yii::$app->security->generateRandomString(12));
            $model->status = User::STATUS_ACTIVE;
            $model->generateAuthKey();
            $this->sendEmail($model);

            if ($model->save()) {
                $userDep = UserDepartment::findOne(['user_id' => Y::user()->id]);
                $userDepartmentModel->department_id = $userDep->department_id;
                $userDepartmentModel->user_id = $model->id;
                if ($userDepartmentModel->save()) {
                    return $this->redirect(['view', 'id' => $model->id]);
                } else {
                    Yii::error($userDepartmentModel->errors, '123');
                }
            } else {
                Yii::error($model->errors, '123');
            }

        }

        Yii::debug($model->toArray(), '123');
        Yii::debug($workPosModel->toArray(), '123');
        Yii::debug($userDepartmentModel->toArray(), '123');

        return $this->render('create', [
            'model' => $model,
            'workPosModel' => $workPosModel,
            'fileModel'=>$fileModel
        ]);

    }

    function sendEmail(User $user) {
        if (!User::isPasswordResetTokenValid($user->password_reset_token)) {
            $user->generatePasswordResetToken();
            if (!$user->save()) {
                return false;
            }
        }

        return Yii::$app
            ->mailer
            ->compose(
                ['html' => 'passwordResetToken-html', 'text' => 'passwordResetToken-text'],
                ['user' => $user]
            )
            ->setFrom([Yii::$app->params['supportEmail'] => Yii::$app->name ])
            ->setTo($user->email)
            ->setSubject('Password reset for ' . Yii::$app->name)
            ->send();
    }

    /**
     * Updates an existing User model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id) {
        $model = $this->findModel($id);
        $userTheme = new UserTheme();
        $auth = Y::auth();
        $permissions = $auth->getPermissions();

        $roles = false;
        foreach ($permissions as $permission) {
            if(strpos($permission->name, "access_") !== false){
                $roles[] = $permission;
            }
        }

        $themes = ArrayHelper::map(Theme::find()->select(['id', 'name'])->all(), 'id', 'name');

        $checked = ArrayHelper::toArray(UserTheme::find()->select(['theme_id'])->where(['user_id' => $id])->all());
        $checked1 = [];
        foreach ($checked as $item) {
            array_push($checked1, $item['theme_id']);
        }
        $userTheme->permissions = $checked1;

        if ($userTheme->load(Yii::$app->request->post())) {
            $themeList = $_POST['UserTheme']['permissions'];

            UserTheme::deleteAll(['user_id' => $id]);

            if (!empty($themeList[0])) {
                foreach ($themeList as $theme) {
                    if (!empty($theme)) {
                        $userTheme = new UserTheme();
                        $userTheme->user_id = $id;
                        $userTheme->theme_id = $theme;
                        $userTheme->save();
                    }
                }
            }

            //КОСТЫЛЬ ЖЕСТЬ КОШМАР НАДО ПОДДЕРЖКУ НЕСКОЛЬКИХ ОРГАНИЗАЦИЙ ИЛИ ДЕПАРТАМЕНТОВ
            return $this->redirect(['org', 'id' => UserDepartment::findOne(['user_id' => $id])->department_id]);
        }

        return $this->render('update-rules', [
            'model' => $model,
            'userTheme' => $userTheme,
            'themes' => $themes,
            'checked' => $checked1,
            'roles' => $roles,
        ]);
    }

    protected function findModel($id) {
        if (($model = User::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionLoginAs($id){
        $initialId = Yii::$app->user->getId(); //here is the current ID, so you can go back after that.
        if ($id == $initialId) {
            return $this->redirect('/site/index');
        } else {
            $user = User::findOne($id);
            $duration = 0;
            Yii::$app->user->switchIdentity($user, $duration); //Change the current user.
            Yii::$app->session->set('user.idbeforeswitch',$initialId); //Save in the session the id of your admin user.
            return $this->redirect('/site/index'); //redirect to any page you like.
        }
    }
    public function actionLoginStop(){
        $originalId = Yii::$app->session->get('user.idbeforeswitch');
        if ($originalId) {
            $user = User::findOne($originalId);
            $duration = 0;
            Yii::$app->user->switchIdentity($user, $duration);
            Yii::$app->session->remove('user.idbeforeswitch');
        }
        return $this->redirect('/site/index');
    }
    public function actionLogin(){
        return $this->render('login');
    }
}
