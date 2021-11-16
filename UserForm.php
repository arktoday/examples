<?php


namespace kip\models;


use common\models\User;
use common\models\UserDepartment;
use Yii;
use yii\base\BaseObject;
use yii\helpers\Json;

class UserForm extends \yii\base\Model {
    public $first_name;
    public $last_name;
    public $second_name;
    public $work_position;
    public $user_departments;


    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['first_name', 'second_name', 'second_name', 'work_position'], 'string'],
        ];
    }

    /**
     * Signs user up.
     *
     * @return bool whether the creating new account was successful and email was sent
     */
    public function create()
    {
        if (!$this->validate()) {
            return null;
        }


        $user = new User();
        $user->username = $this->username;
        $user->email = $this->email;
        $user->first_name = $this->first_name;
        $user->last_name = $this->last_name;
        $user->second_name = $this->second_name;
        $user->work_position = $this->work_position;

        $user->setPassword($this->password);
        $user->generateAuthKey();
        $user->generateEmailVerificationToken();

        if(!$user->save()){
            return false;
        }

        if($this->user_departments){
            foreach ($this->user_departments as $user_departmentID) {
                $userDep = new UserDepartment();
                $userDep->user_id = $user->id;
                $userDep->department_id = $user_departmentID;
                if (!$userDep->save()){
                    Yii::$app->session->setFlash('error', "Ошибка добавления департамента $user_departmentID пользователю $user->id ");
                }
            }
        }

        return true;
    }

}