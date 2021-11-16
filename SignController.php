<?php

namespace kip\controllers;

use common\components\helpers\Y;
use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SignController extends Controller {
    public function actionIndex() {
        return $this->render('index');
    }

    public function actionVerify() {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $post = Yii::$app->request->post();
        if (!isset($post['cert'])) {
            throw new NotFoundHttpException("Не задан сертификат пользователя. Задайте его в личном кабинете.");
        }
        if(Y::user()->cert === $post['cert']){
            throw new ForbiddenHttpException("Сертификат не совпадает.");
        }
        return ["message" => "success"];
    }
}
