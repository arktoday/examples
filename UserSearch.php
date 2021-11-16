<?php

namespace kip\models;

use common\models\Department;
use common\models\UserDepartment;
use yii\base\BaseObject;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\User;
use yii\helpers\ArrayHelper;

/**
 * UserSearch represents the model behind the search form of `common\models\User`.
 */
class UserSearch extends User {
    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['id', 'status', 'created_at', 'updated_at'], 'integer'],
            [['username', 'fullname', 'auth_key', 'password_hash', 'password_reset_token', 'email', 'verification_token', 'first_name', 'second_name', 'last_name', 'work_position_id'], 'safe'],
            [[ 'first_name', 'second_name', 'last_name',], 'string'],
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
    public function search($params) {
        $query = User::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ]);

        $query->andFilterWhere(['like', 'username', $this->username])
            ->andFilterWhere(['like', 'auth_key', $this->auth_key])
            ->andFilterWhere(['like', 'password_hash', $this->password_hash])
            ->andFilterWhere(['like', 'password_reset_token', $this->password_reset_token])
            ->andFilterWhere(['like', 'email', $this->email])
            ->andFilterWhere(['like', 'verification_token', $this->verification_token])
            ->andFilterWhere(['like', 'first_name', $this->first_name])
            ->andFilterWhere(['like', 'second_name', $this->second_name])
            ->andFilterWhere(['like', 'last_name', $this->last_name])
            ->andFilterWhere(['like', 'work_position_id', $this->work_position_id]);

        return $dataProvider;
    }

    public function searchByComp($params) {
        $query = User::find();
        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'in',
            'id',
            User::find()->select('id')
                ->where(['id' => UserDepartment::find()->select('user_id')
                    ->where(['department_id' => UserDepartment::find()
                        ->where(['user_id' => \common\components\helpers\Y::user()->id])->one()->department_id
                    ])
                ])->all()
        ]);

//        $query->andFilterWhere(['like', 'id', \common\components\helpers\Y::user()->id]);


        return $dataProvider;
    }

    public function searchMin($params, $depId) {
        $query = User::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
//            'id' => $this->id,
//            'status' => $this->status,
//            'created_at' => $this->created_at,
//            'updated_at' => $this->updated_at,
        ]);

        //        $query->andFilterWhere(['like', 'username', $this->username])
        //            ->andFilterWhere(['like', 'auth_key', $this->auth_key])
        //            ->andFilterWhere(['like', 'password_hash', $this->password_hash])
        //            ->andFilterWhere(['like', 'password_reset_token', $this->password_reset_token])
        //            ->andFilterWhere(['like', 'email', $this->email])
        //            ->andFilterWhere(['like', 'verification_token', $this->verification_token])
        //            ->andFilterWhere(['like', 'first_name', $this->first_name])
        //            ->andFilterWhere(['like', 'second_name', $this->second_name])
        //            ->andFilterWhere(['like', 'last_name', $this->last_name])
        //            ->andFilterWhere(['like', 'work_position_id', $this->work_position_id]);
        //






//        \Krumo::dump(ArrayHelper::map(UserDepartment::findAll(['department_id'=>$depId]), 'id', 'user_id'));
//        die;
        try {
            $query->andFilterWhere([
                'in',
                'id',
                ArrayHelper::map(UserDepartment::findAll(['department_id'=>$depId]), 'id', 'user_id')
            ])/*->andFilterWhere(['not', ['work_position_id' => 'null']])*/;
        } catch (\Exception $exception) {
            $query->where('0=1');
            return $dataProvider;
        }


        return $dataProvider;
    }
}
