<?php

namespace kip\controllers;

use common\components\helpers\Y;
use common\models\MetricDataExport;
use common\models\Request;
use common\models\RequestStatus;
use common\models\UserDepartment;
use DateInterval;
use DatePeriod;
use DateTime;
use iacmon\components\export\ExporterSuperTBS;
use iacmon\components\export\ExporterXLSXWriter;
use kip\components\analyst\FullExportTemplate;
use kip\components\analyst\Metric;
use kip\components\analyst\RequestMetric;
use kip\components\analyst\RequestResponseMetric;
use kip\components\AnalystManager;
use kip\models\AnalystForm;
use kip\models\RequestSearch;
use Yii;
use yii\base\BaseObject;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\HttpException;
use yii\web\Response;

class AnalystController extends Controller {

    public function actionMetricTypes() {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return AnalystManager::getMap();
    }

    public function actionMetrics($metric_type) {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $result = [];
        /** @var Metric $metric */
        $metric = new $metric_type();
        $labels = $metric->getLabels();
        foreach (get_class_methods($metric_type) as $method) {
            if (isset($labels[str_replace('filter', '', $method)])) {
                $result[$method] = $labels[str_replace('filter', '', $method)]['label'];
            }
        }
        return $result;
    }

    public function actionMetricsParams($metric_type, $function) {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $result = [1, 2, 3, 4];
        /** @var Metric $metric */
        return $result;
    }


    public function actionIndex() {
        $metricDataExport = new MetricDataExport();
        $dataProvider = new ActiveDataProvider([
            'query' => MetricDataExport::find(),
        ]);

        return $this->render('index', [
            'metricDataExport' => $metricDataExport,
            'dataProvider' => $dataProvider
        ]);
    }

    public function actionExport($download = 1) {
        $exportTemplate = new FullExportTemplate(Yii::$app->request->post());
        $result = $exportTemplate->getResult();

        if ($download) {
            $exporter = new ExporterXLSXWriter($result);
            $exporter->init();
            $exporter->writer->NoErr = true;
            return $exporter->output();
        } else {
            \Krumo::dump($result, KRUMO_EXPAND_ALL);
            die;
        }


    }
}
