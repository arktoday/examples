<?php

namespace kip\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Component;

/**
 * ComponentSearch represents the model behind the search form of `common\models\Component`.
 */
class ComponentSearch extends Component
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'user_id', 'version', 'component_type_id'], 'integer'],
            [['code', 'title', 'description', 'options', 'date_created', 'date_updated'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
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
    public function search($params)
    {
        $query = Component::find();

        // add conditions that should always apply here

        $query->andWhere(['deleted' => 0]);

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
            'date_created' => $this->date_created,
            'date_updated' => $this->date_updated,
//            'user_id' => $this->user_id,
            'version' => $this->version,
            'component_type_id' => $this->component_type_id,
        ]);

        $query->andFilterWhere(['like', 'code', $this->code])
            ->andFilterWhere(['like', 'title', $this->title])
            ->andFilterWhere(['like', 'description', $this->description])
            ->andFilterWhere(['like', 'options', $this->options]);

        return $dataProvider;
    }
}
