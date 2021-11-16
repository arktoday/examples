<?php

namespace kip\controllers;

use common\components\helpers\Y;
use common\components\Jwt;
use common\models\LoginForm;
use common\models\User;
use Firebase\JWT\JWT as FireBaseJWT;
use Yii;
use yii\base\BaseObject;
use yii\helpers\Json;

class ApiController extends \yii\web\Controller {
    public function actionLogin() {
        if(!Yii::$app->user->isGuest){
            return $this->redirect('/');
        }
        $jwt = new \common\components\Jwt();
        $tokenString = \Yii::$app->request->get('access_token');
        try {
            $token = FireBaseJWT::decode($tokenString, Yii::$app->params['jwt']['secret'], ['HS512']);
        } catch (\Exception $exception) {
            header('HTTP/1.1 401 Unauthorized');
            exit;
        }
        if($token->user_id){
            Yii::$app->user->login(User::findOne(['id'=>$token->user_id]), 3600 * 24 * 30);
        }
        return $this->redirect('/');
    }

}