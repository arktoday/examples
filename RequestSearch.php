<?php

namespace kip\models;

use common\components\helpers\Y;
use common\models\CuratorOrg;
use common\models\Department;
use common\models\RequestStatus;
use common\models\RequestStatusRole;
use common\models\UserDepartment;
use Exception;
use kartik\daterange\DateRangeBehavior;
use Yii;
use yii\base\BaseObject;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Request;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\web\User;

/**
 * RequestSearch represents the model behind the search form of `common\models\Request`.
 */
class RequestSearch extends Request {
    const COOKIE_KEY = "request_search";
    public $createTimeRange;
    public $datetime_min;
    public $datetime_max;

    public function behaviors() {
        return [
            [
                'class' => DateRangeBehavior::className(),
                'attribute' => 'date_created',
                'dateStartAttribute' => 'datetime_min',
                'dateEndAttribute' => 'datetime_max',
            ]
        ];
    }


    public function saveSearch() {
        $savedValue = (bool) Yii::$app->request->get('clear');
        Yii::debug(
            [
                "saved RequestSearch",
                $this->toArray()
            ]
        );
        Yii::$app->response->cookies->add(new \yii\web\Cookie([
            'name' => RequestSearch::COOKIE_KEY,
            'value' => $savedValue ? "" : serialize($this->toArray()),
        ]));
    }

    public function getSearch() {
        $cookies = Yii::$app->request->cookies;
        $result = @unserialize($cookies->getValue(self::COOKIE_KEY, ''));

        if(Yii::$app->request->get('clear')){
            $result = [];
            $this->saveSearch();
        }

        if(Yii::$app->request->get('debug')){
            \Krumo::dump($result);
            die;
        }



        return $result;
    }

    public function __construct($config = []) {
        parent::__construct($config);
    }

    public function load($data, $formName = null) {
        parent::load($data, $formName);
        $this->saveSearch();
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [
                [
                    'id',
                    'user_id',
                    'curator_id',
                    //                    'department_id',
                    'is_official',
                    'org_id',
                    'priority_id',
                    'scan_file_id',
                    'sub_department_id',
                    'request_status_id',
                    'response_id',
                    'theme_id',
                    'sub_theme_id',
                    'resp_user_id'
                ],
                'integer',
            ],
            [['title', 'content', 'date_created', 'curator_id'], 'safe'],
            ['date_created', 'match', 'pattern' => '/[^0-9.-]/'],
            [['createTimeRange'], 'match', 'pattern' => '/^.+\s\-\s.+$/'],
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function scenarios() {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params = []) {
        Yii::debug(['search$params' => $params, 'request this' => $this->toArray()], 'search');
        $query = $this->getRequests();
        $auth = Y::auth();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 50,
            ],
            'sort' => ['attributes' => [
                'date_created',
                'title',
                'user_id',
                'resp_user_id',
                'is_official',
                'priority_id',
                'request_status_id',
                //                'user.fullName' => [
//                    'asc' => ['u.last_name' => SORT_ASC],
//                    'desc' => ['u.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
//                'respUser.fullName' => [
//                    'asc' => ['ru.last_name' => SORT_ASC],
//                    'desc' => ['ru.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
            ]],
        ]);

        if ($params)
            $this->load($params);

        try {
            if (!$this->validate()) {
                $query->where('0=1');

                return $dataProvider;
            }
        } catch (Exception $e) {
        }


        foreach ($auth->getRolesByUser(Y::user()->id) as $item) {
            $roleName = $item->name;
            switch ($roleName) {
                case "minobr_member":
                case "minobr_boss":
                    $query->andFilterWhere([
                        'sub_department_id' => UserDepartment::find()->where(['user_id' => Y::user()->id])->one()->department_id,
                    ]);
                    //                    $query->orFilterWhere([
                    //                        'watcher_user_id' => Y::user()->id,
                    //                        'resp_user_id' => Y::user()->id,
                    //                    ]);
                    break;
                case "curator":
                    $query->andFilterWhere([
                        'curator_id' => Y::user()->id,
                    ]);
                    break;
            }
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'request_status_id' => $this->request_status_id,
            'curator_id' => $this->curator_id,

            'sub_department_id' => $this->sub_department_id,
            'theme_id' => $this->theme_id,
            'org_id' => $this->org_id,
        ]);

        $query = $this->filterQueryByStatus($query);

        //        $query->andFilterWhere(['like', 'title', $this->title])->andFilterWhere(['like', 'content', $this->content]);

        if ($this->datetime_min && $this->datetime_max)
            $query->andFilterWhere(['>=', 'date_created', date('Y-m-d', $this->datetime_min)])
                ->andFilterWhere(['<', 'date_created', date('Y-m-d', $this->datetime_max)]);
        return $dataProvider;
    }

    public function getFilteredQuery($query) {
        $code = Yii::$app->request->get('filter_code');
        if (!$code) {
            $code = Yii::$app->request->cookies->getValue('request_menu_item_selected');
        }
        $item = Request::GetFilterItems($code);

        $query->andFilterWhere([
            'resp_user_id'=>$this->resp_user_id,
            'sub_theme_id'=>$this->sub_theme_id,
            'theme_id'=>$this->theme_id,
        ]);

        return isset($item['getItems']) ? $item['getItems']($query) : $query;
    }

    public function getRequests() {
        $query = Request::find();
        //для сортировки по фио
//        $query->innerJoin('user as u','u.id = request.user_id');
//        $query->innerJoin('user as ru','ru.id = request.resp_user_id');
        //        $query->orderBy(['date_created'=>SORT_DESC]);


        if ($searchArray = $this->getSearch()) {
            if (count(array_filter($searchArray, function ($value) {
                    return !is_null($value) && $value !== '';
                })) > 0) {

                $this->setAttributes($searchArray);
                Yii::debug(["Request Search set to " => $searchArray], 'request');
            }
        }

//        \Krumo::dump($this->toArray());
//        die;

        if ($status = Yii::$app->request->get('status')) {
            if ($status != 0) {
                if ($status == 3 || $status == 1 || $status == 2) { // для статуса в работе нужно еще и просроченные задачи выводить
                    $query->andFilterWhere([
                        'request_status_id' => [$status, 4], // 4 - просроченные
                    ]);
                } else {
                    $query->andFilterWhere(['request_status_id' => $status,]);
                }
            }
        }

        return $this->getFilteredQuery($query);
    }

    public function searchMy($params) {
        $query = $this->getRequests();
        $query->andWhere(['user_id' => Y::user()->id]);
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['attributes' => [
                'date_created',
                'title',
                'user_id',
                'resp_user_id',
                'is_official',
                'priority_id',
                'request_status_id',
//                'user.fullName' => [
//                    'asc' => ['u.last_name' => SORT_ASC],
//                    'desc' => ['u.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
//                'respUser.fullName' => [
//                    'asc' => ['ru.last_name' => SORT_ASC],
//                    'desc' => ['ru.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
            ]],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'request_status_id' => $this->request_status_id,
            'curator_id' => $this->curator_id,
        ]);

        return $dataProvider;
    }


    //filtering not working...
    private function getRequestsByRole() {

        $userRoles = explode("|", implode("|", ArrayHelper::map(Yii::$app->authManager->getRolesByUser(Y::user()->id), 'name', 'name')));
        Yii::debug($userRoles);
        $requestRoles = ArrayHelper::map(RequestStatusRole::find()->where([
            'in',
            'role',
            $userRoles,
        ])->all(), 'id', 'request_status_id');

        $query = $this->getRequests();
        $query->orderBy('id DESC');
        $query->filterWhere([
            'in',
            'request_status_id',
            ArrayHelper::map(RequestStatus::find()->select(['id'])->where([
                'in',
                'id',
                $requestRoles,
            ])->all(), 'id', 'id'),
        ]);

        return $query;
    }

    public function searchByComp($params) {
        $query = $this->getRequests();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['attributes' => [
                'date_created',
                'title',
                'user_id',
                'resp_user_id',
                'is_official',
                'priority_id',
                'request_status_id',
                //                'user.fullName' => [
//                    'asc' => ['u.last_name' => SORT_ASC],
//                    'desc' => ['u.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
//                'respUser.fullName' => [
//                    'asc' => ['ru.last_name' => SORT_ASC],
//                    'desc' => ['ru.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
            ]],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }
        $query = $this->filterQueryByStatus($query);
        $query->andFilterWhere([
            'in',
            'user_id',
            UserDepartment::find()->select(['user_id'])->where([
                'department_id' => UserDepartment::find()->where(['user_id' => Y::user()->id])->one()->department_id,
            ])->all(),
        ]);

        $query->andFilterWhere([
            'sub_department_id' => $this->sub_department_id,
            'theme_id' => $this->theme_id,
        ]);


        return $dataProvider;
    }

    public function searchByDepartmentId($params, $userId, $status) {
        $query = $this->getRequestsByRole();
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['attributes' => [
                'date_created',
                'title',
                'user_id',
                'resp_user_id',
                'is_official',
                'priority_id',
                'request_status_id',
                //                'user.fullName' => [
//                    'asc' => ['u.last_name' => SORT_ASC],
//                    'desc' => ['u.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
//                'respUser.fullName' => [
//                    'asc' => ['ru.last_name' => SORT_ASC],
//                    'desc' => ['ru.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
            ]],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'sub_department_id' => UserDepartment::find()->where(['user_id' => $userId])->one()->department_id,
        ]);

        $query->orFilterWhere(['watcher_user_id' => Y::user()->id]);

        if ($status != 0) {
            if ($status == 3 || $status == 1 || $status == 2) { // для статуса в работе нужно еще и просроченные задачи выводить
                $query->andFilterWhere([
                    'request_status_id' => [$status, 4], // 4 - просроченные
                ]);
            } else
                $query->andFilterWhere([
                    'request_status_id' => $status,
                ]);
        }
        $query = $this->getFilteredQuery($query);

        $query->andFilterWhere([
            'curator_id' => $this->curator_id,
            'org_id' => $this->org_id,
        ]);

        $query = $this->filterByDateRange($query);

        return $dataProvider;
    }

    public function searchByOtdId($params, $id, $status) {
        $query = $this->getRequests();
        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['attributes' => [
                'date_created',
                'title',
                'user_id',
                'resp_user_id',
                'is_official',
                'priority_id',
                'request_status_id',
                //                'user.fullName' => [
//                    'asc' => ['u.last_name' => SORT_ASC],
//                    'desc' => ['u.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
//                'respUser.fullName' => [
//                    'asc' => ['ru.last_name' => SORT_ASC],
//                    'desc' => ['ru.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
            ]],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'sub_department_id' => $id,
        ]);
        $query->orFilterWhere(['watcher_user_id' => Y::user()->id]);


        if ($status != 0) {
            if ($status == 3 || $status == 1 || $status == 2) { // для статуса в работе нужно еще и просроченные задачи выводить
                $query->andFilterWhere([
                    'request_status_id' => [$status, 4], // 4 - просроченные
                ]);
            } else
                $query->andFilterWhere([
                    'request_status_id' => $status,
                ]);
        }

        $query = $this->filterByDateRange($query);
        return $dataProvider;
    }

    private function filterByDateRange($query) {

        if ($this->datetime_min && $this->datetime_max)
            $query->andFilterWhere(['>=', 'date_created', date('Y-m-d', $this->datetime_min)])
                ->andFilterWhere(['<', 'date_created', date('Y-m-d', $this->datetime_max)]);
        return $query;
    }

    public function searchByDepId($params, $id, $status) {
        $query = $this->getRequests();
        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['attributes' => [
                'date_created',
                'title',
                'user_id',
                'resp_user_id',
                'is_official',
                'priority_id',
                'request_status_id',
                //                'user.fullName' => [
//                    'asc' => ['u.last_name' => SORT_ASC],
//                    'desc' => ['u.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
//                'respUser.fullName' => [
//                    'asc' => ['ru.last_name' => SORT_ASC],
//                    'desc' => ['ru.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
            ]],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $depHeadsId = Department::find()->select(['id'])->where(['parent_id' => $id])->all();
        $depHeadsId = ArrayHelper::map($depHeadsId, 'id', 'id');
        $otdsId = Department::find()->select(['id'])->where(['in', 'parent_id', $depHeadsId])->all();
        $otdsId = ArrayHelper::map($otdsId, 'id', 'id');

        // grid filtering conditions
        $query->andFilterWhere([
            'in',
            'sub_department_id',
            $otdsId
        ]);

        if ($status != 0) {
            if ($status == 3 || $status == 1 || $status == 2) { // для статуса в работе нужно еще и просроченные задачи выводить
                $query->andFilterWhere([
                    'request_status_id' => [$status, 4], // 4 - просроченные
                ]);
            } else
                $query->andFilterWhere([
                    'request_status_id' => $status,
                ]);
        }
        $query = $this->getFilteredQuery($query);

        $query = $this->filterByDateRange($query);

        return $dataProvider;
    }

    public function searchByDeputyMin($params, $id, $status) {
        $query = $this->getRequests();
        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['attributes' => [
                'date_created',
                'title',
                'user_id',
                'resp_user_id',
                'is_official',
                'priority_id',
                'request_status_id',
                //                'user.fullName' => [
//                    'asc' => ['u.last_name' => SORT_ASC],
//                    'desc' => ['u.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
//                'respUser.fullName' => [
//                    'asc' => ['ru.last_name' => SORT_ASC],
//                    'desc' => ['ru.last_name' => SORT_DESC],
//                    'default' => SORT_DESC
//                ],
            ]],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            //             $query->where('0=1');
            return $dataProvider;
        }

        $depsId = Department::find()->select(['id'])->where(['parent_id' => $id])->all();
        $depsId = ArrayHelper::map($depsId, 'id', 'id');
        $zamiId = Department::find()->select(['id'])->where(['in', 'parent_id', $depsId])->all();
        $zamiId = ArrayHelper::map($zamiId, 'id', 'id');
        $otdsId = Department::find()->select(['id'])->where(['in', 'parent_id', $zamiId])->all();
        $otdsId = ArrayHelper::map($otdsId, 'id', 'id');

        // grid filtering conditions
        $query->andFilterWhere([
            'in',
            'sub_department_id',
            $otdsId
        ]);

        if ($status != 0) {
            if ($status == 3 || $status == 1 || $status == 2) { // для статуса в работе нужно еще и просроченные задачи выводить
                $query->andFilterWhere([
                    'request_status_id' => [$status, 4], // 4 - просроченные
                ]);
            } else

                $query->andFilterWhere([
                    'request_status_id' => $status,
                ]);
        }

        return $dataProvider;
    }

    public function filterQueryByStatus($query): ActiveQuery {
        $userRoles = explode("|", implode("|", ArrayHelper::map(Yii::$app->authManager->getRolesByUser(Y::user()->id), 'name', 'name')));

        $requestRoles = ArrayHelper::map(RequestStatusRole::find()->where([
            'in',
            'role',
            $userRoles,
        ])->all(), 'id', 'request_status_id');

        $query->andFilterWhere([
            'in',
            'request_status_id',
            ArrayHelper::map(RequestStatus::find()->select(['id'])->where([
                'in',
                'id',
                $requestRoles,
            ])->all(), 'id', 'id'),
        ]);
        return $query;
    }


}
