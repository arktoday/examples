<?php

namespace common\models;

use common\components\helpers\Y;
use common\models\Department;
use kartik\daterange\DateRangeBehavior;
use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\web\IdentityInterface;

/**
 * User model
 *
 * @property integer $id
 * @property string $username
 * @property string $password_hash
 * @property string $password_reset_token
 * @property string $verification_token
 * @property string $email
 * @property string $auth_key
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $last_visit
 * @property integer $profile_photo_id
 * @property string $password write-only password
 * @property int|null $address_id
 * @property string|null $office
 * @property string|null $birthday
 * @property string|null $city_phone
 * @property int|null $is_email_notifications
 * @property int|null $delegated_user_id
 * @property string|null $date_delegated_due_start
 * @property string|null $date_delegated_due_end
 * @property string|null $reffer_http
 *
 * @property Department[] $departments
 * @property Department $org
 * @property Request[] $requests
 * @property Task[] $tasks
 * @property TaskChange[] $taskChanges
 * @property TaskResp[] $taskResps
 * @property TaskWatcher[] $taskWatchers
 * @property UserAccess[] $userAccesses
 * @property UserDepartment[] $userDepartments
 * @property UserProject[] $userProjects
 * @property Project[] $projects
 * @property AddressUser $address
 * @property string $first_name [varchar(255)]
 * @property string $second_name [varchar(255)]
 * @property string $last_name [varchar(255)]
 * @property string $fullName
 * @property string $initials
 * @property string $cert
 * @property int $work_position_id [int(11)]
 * @property string $work_position [varchar(255)]
 * @property WorkPosition $workPosition
 * @property File $profilePhoto
 * @property string $work_phone
 * @property string $inner_phone
 * @property boolean $isMinobr
 * @property boolean $isOrg
 * @property Conversation $conversations
 * @property bool $isOnline
 * @property bool $isProfileFull
 * @property User $delegatedUser
 * @property User $resp
 *
 */
class User extends ActiveRecord implements IdentityInterface {
    public $date_delegated_due_range;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['date_delegated_due_range'], 'match', 'pattern' => '/^.+\s\-\s.+$/'],
            [['reffer_http'], 'string', 'max' => 256],
            [['username', 'email'], 'required'],
            [['username', 'email'], 'unique'],
            [['email'], 'email'],
            "main_fields" => [['first_name', 'second_name', 'last_name', 'email', 'username', 'work_phone', 'work_position', 'work_phone', 'inner_phone', 'city_phone'], 'string'],
            [['cert'], 'string'],
            [['work_position_id', 'profile_photo_id', 'address_id', 'delegated_user_id'], 'integer'],
            [['work_phone', 'office'], 'string', 'max' => 100],
            ['status', 'default', 'value' => self::STATUS_INACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_DELETED]],
            [
                ['work_position_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => WorkPosition::className(),
                'targetAttribute' => ['work_position_id' => 'id'],
            ],
            [
                ['profile_photo_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => File::className(),
                'targetAttribute' => ['profile_photo_id' => 'id'],
            ],
            [
                ['address_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => AddressUser::className(),
                'targetAttribute' => ['address_id' => 'id']
            ],
            [['new_pass', 'birthday', 'last_visit', 'is_email_notifications', 'date_delegated_due_end', 'date_delegated_due_start'], 'safe']
        ];
    }

    public function attributeLabels() {
        return [
            "id" => "ID",
            "username" => "Логин",
            "email" => "Почта",
            "last_name" => "Фамилия",
            "first_name" => "Имя",
            "second_name" => "Отчество",
            "work_position_id" => "Должность",
            "work_position" => "Должность",
            "fullname" => "ФИО",
            "profile_photo_id" => "Фото пользователя",
            "work_phone" => "Рабочий телефон",
            "inner_phone" => "Внутренний телефон",
            "city_phone" => "Городской телефон",
            "new_pass" => "Новый пароль",
            'address_id' => 'Адрес',
            'office' => 'Кабинет',
            'birthday' => 'День рождения',
            'last_visit' => 'Последний визит',
            'fullName' => 'Сотрудник',
            'is_email_notifications' => 'Получать уведомления на электронную почту',
            'delegated_user_id' => 'Сотрудник с делегированными полномочиями',
            'date_delegated_due_end' => 'Дата окончания полномочий',
            'date_delegated_due_start' => 'Дата начала полномочий',
        ];
    }

    public $new_pass = "";

    public function getTaskList($statuses = []) {
        $temp = [];
        foreach ($this->tasks as $task) {
            if (!in_array($task->status->id, $statuses))
                $temp[] = $task;
        }
        return $temp;
    }

    public function getAddress() {
        return $this->hasOne(AddressUser::className(), ['id' => 'address_id']);
    }

    public function getIsOnline() {
        return $this->last_visit && strtotime("$this->last_visit + 5 minutes") > time();
    }

    public function getIsProfileFull() {
        $requiredFields = $this->rules()["main_fields"][0];
        $fields = $this->toArray();
        foreach ($requiredFields as $requiredFieldKey) {
            if (!@$fields[$requiredFieldKey]) {
                return false;
            }
        }

        return (bool)$this->profile_photo_id;
    }

    public function getDelegatedUser() {
        return $this->hasOne(User::className(), ['id' => 'delegated_user_id']);
    }

    public function getResp() {
        $user = $this;
        $formatter = Yii::$app->formatter;

        if (
            @$this->delegatedUser
            && $formatter->asTimestamp($this->date_delegated_due_start) < time()
            && $formatter->asTimestamp($this->date_delegated_due_end) > time()
        ) {
            $user = $this->delegatedUser;
        }
        return $user;
    }

    public function getOrg() {
        foreach ($this->departments as $department) {
            if ($department->org_id)
                return $department;
        }
        return null;
    }


    public function getTasks() {
        $respTasks = TaskResp::findAll(['user_id' => $this->id]);
        $respExpert = TaskExpert::findAll(['user_id' => $this->id]);
        $respWatcher = TaskWatcher::findAll(['user_id' => $this->id]);

        //        \Krumo::dump([
        //            $respTasks, $respExpert, $respWatcher
        //        ]);
        //die;
        $tasks = [];
        foreach (array_merge($respTasks, $respExpert, $respWatcher) as $taskx) {
            $tasks[$taskx->task_id] = $taskx->task;
        }
        foreach (Task::findAll(['author_id' => Y::user()->id]) as $task) {
            $tasks[$task->id] = $task;
        }

        return array_reverse($tasks, true);
    }

    public function getFullName() {
        return implode(" ", [$this->last_name, $this->first_name, $this->second_name]);
    }

    public function getInitials() {
        return $this->last_name . " " . implode("", [
                mb_substr($this->first_name, 0, 1),
                mb_substr($this->second_name, 0, 1),
            ]);
    }

    public function getDepartments() {
        $userDepts = UserDepartment::find()->where(['user_id' => $this->id])->all();
        $depts = [];
        foreach ($userDepts as $userDept) {
            $depts[] = $userDept->department;
        }
        return $depts;
    }

    public function getWorkPosition() {
        return $this->hasOne(WorkPosition::className(), ['id' => 'work_position_id']);
    }

    public function getUserProjects(): ActiveQuery {
        return $this->hasMany(Project::className(), ['id' => 'project_id'])->viaTable('user_project', ['user_id' => 'id']);
    }

    public function getConversations(): ActiveQuery {
        return $this->hasMany(Conversation::className(), ['id' => 'conversation_id'])->viaTable('conversation_user', ['user_id' => 'id']);
    }

    public function getProjects() {
        $temp = [];
        foreach (array_merge($this->userProjects, Project::findAll(['author_id' => $this->id])) as $project) {
            $temp[$project->id] = $project;
        }

        return $temp;
    }

    const STATUS_DELETED = 0;
    const STATUS_INACTIVE = 9;
    const STATUS_ACTIVE = 10;

    /**
     * Gets query for [[UserAccesses]].
     * @return ActiveQuery
     */
    public function getUserAccesses() {
        if (!Yii::$app->user->isGuest)
            return $this->hasMany(UserAccess::className(), ['user_id' => 'id']);
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'user';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors() {
        return [
            TimestampBehavior::className(),
            [
                'class' => DateRangeBehavior::className(),
                'attribute' => 'date_delegated_due_range',
                'dateStartAttribute' => 'date_delegated_due_start',
                'dateEndAttribute' => 'date_delegated_due_end',
                'dateStartFormat' => 'Y-m-d H:i:s',
                'dateEndFormat' => 'Y-m-d H:i:s',
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id) {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * {@inheritdoc}
     */
    //    public static function findIdentityByAccessToken($token, $type = null) {
    //        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    //    }
    public static function findIdentityByAccessToken($token, $type = null) {
        return static::find()
            ->where(['id' => (string)$token->getClaim('uid')])
            //            ->andWhere(['<>', 'usr_status', 'inactive'])  //adapt this to your needs
            ->one();
    }
    //    public function afterSave($insert, $changedAttributes) {
    //        if (array_key_exists('password_hash', $changedAttributes)) {
    //            UserRefreshToken::deleteAll(['id' => $this->id]);
    //        }
    //
    //        parent::afterSave($insert, $changedAttributes);
    //    }
    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username) {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by email
     *
     * @param string email
     * @return static|null
     */
    public static function findByEmail($email, $needStatus = true) {
        if ($needStatus) {
            return static::findOne(['email' => $email, 'status' => self::STATUS_ACTIVE]);
        } else {
            return static::findOne(['email' => $email]);
        }
    }


    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token) {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finds user by verification email token
     *
     * @param string $token verify email token
     * @return static|null
     */
    public static function findByVerificationToken($token) {
        return static::findOne([
            'verification_token' => $token,
            'status' => self::STATUS_INACTIVE,
        ]);
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return bool
     */
    public static function isPasswordResetTokenValid($token) {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int)substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        return $timestamp + $expire >= time();
    }

    /**
     * {@inheritdoc}
     */
    public function getId() {
        return $this->getPrimaryKey();
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey() {
        return $this->auth_key;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey) {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password) {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password) {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey() {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken() {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Generates new token for email verification
     */
    public function generateEmailVerificationToken() {
        $this->verification_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken() {
        $this->password_reset_token = null;
    }

    //props
    //
    //    public function getDepartmentsBoss() {
    //        return $this->hasMany(Department::className(), ['boss_id' => 'id']);
    //    }
    //
    //    public function getRequests() {
    //        return $this->hasMany(Request::className(), ['user_id' => 'id']);
    //    }
    //
    //    public function getTasksAuthor() {
    //        return $this->hasMany(Task::className(), ['author_id' => 'id']);
    //    }
    //
    //    public function getTaskChanges() {
    //        return $this->hasMany(TaskChange::className(), ['author_id' => 'id']);
    //    }
    //
    //    public function getTaskResps() {
    //        return $this->hasMany(TaskResp::className(), ['user_id' => 'id']);
    //    }
    //
    //    public function getRespTasks() {
    //        return $this->hasMany(Task::className(), ['id' => 'task_id'])->viaTable('task_resp', ['user_id' => 'id']);
    //    }
    //
    //
    //    public function getWatchingTasks() {
    //        return $this->hasMany(Task::className(), ['id' => 'task_id'])->viaTable('task_watcher', ['user_id' => 'id']);
    //    }
    //
    //    public function getUserProjects() {
    //        return $this->hasMany(UserProject::className(), ['user_id' => 'id']);
    //    }
    //
    public function getUserDepartments() {
        return $this->hasMany(UserDepartment::className(), ['user_id' => 'id']);
    }

    /**
     * Gets query for [[ProfilePhoto]].
     *
     * @return ActiveQuery
     */
    public function getProfilePhoto() {
        return $this->hasOne(File::className(), ['id' => 'profile_photo_id']);
    }

    public function getIsMinobr() {
        foreach ($this->departments as $department) {
            if($department->org_id)
                return false;
            if ($department->is_minobr)
                return true;
        }
        return false;
    }
    public function getIsOrg() {
        foreach ($this->departments as $department) {
            if ($department->is_minobr)
                return false;
            if($department->org_id)
                return true;
        }
        return false;
    }

    public function beforeSave($insert) {
        //        $this->date_delegated_due_start = date('Y-m-d H:i:s', $this->date_delegated_due_start);
        //        $this->date_delegated_due_end = date('Y-m-d H:i:s', $this->date_delegated_due_end);
        //        \Krumo::dump([
        //            $this->date_delegated_due_start,
        ////            date('Y-m-d H:i:s', $this->date_delegated_due_start)
        //        ]);
        //        die;
        return parent::beforeSave($insert); // TODO: Change the autogenerated stub
    }


    public static function convert($userObject) {
        //        return $userObject;
        if (is_array($userObject)) {
            $userResps = [];
            /** @var User $user */
            foreach ($userObject as $user) {
                $userResps[] = $user->resp;
            }
            return $userResps;
        }

        return is_object($userObject) ? $userObject->resp : [];
    }

    public static function convertId($userIds) {
        //        return $userIds;
        if (is_array($userIds)) {

            $userResps = [];
            /** @var User $user */
            $users = self::findAll(['id' => $userIds]);
            foreach ($users as $user) {
                $userResps[] = $user->resp->id;
            }
            return $userResps;
        }

        return @self::findOne(['id' => $userIds])->resp->id ? self::findOne(['id' => $userIds])->resp->id : [];
    }

    public static function getDropdownUsers($users = null){
        $minobrDepartments = Department::findMinobr()->all();
        $users = $users ?: [];

        if(!$users){
            foreach ($minobrDepartments as $minobrDepartment) {
                foreach ($minobrDepartment->users as $user) {
                    $users[$user->last_name.$user->id] = $user;
                }
            }
        }
        ksort($users);
        $users = array_values($users); //todo переделать на orderBy

        $result = [];
        foreach ($users as $user) {
            $result[$user->resp->id] = $user->fullName . ($user->resp->id === $user->id ? "" : "(отв. {$user->resp->fullName})");
        }
        return $result;
    }

    public function __toString() {
        return "$this->fullName[$this->id]";
    }
    public static function map($users){
        return ArrayHelper::map($users, 'id', 'fullName');
    }
}
