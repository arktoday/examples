<?php

namespace kip\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\PageComponent;

/**
 * PageComponentSearch represents the model behind the search form of `common\models\PageComponent`.
 */
class PageComponentSearch extends PageComponent
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'page_id', 'component_id', 'user_id', 'version', 'parent_component_id'], 'integer'],
            [['date_created', 'date_updated', 'position'], 'safe'],
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
        $query = PageComponent::find();

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
            'page_id' => $this->page_id,
            'component_id' => $this->component_id,
            'date_created' => $this->date_created,
            'date_updated' => $this->date_updated,
            'user_id' => $this->user_id,
            'version' => $this->version,
            'parent_component_id' => $this->parent_component_id,
        ]);

        $query->andFilterWhere(['like', 'position', $this->position]);

        return $dataProvider;
    }

    public function searchByPageId($params, $page_id)
    {
        $query = PageComponent::find();

        // add conditions that should always apply here

        $query->andWhere(['page_id' => $page_id]);

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
            'page_id' => $this->page_id,
            'component_id' => $this->component_id,
            'date_created' => $this->date_created,
            'date_updated' => $this->date_updated,
            'user_id' => $this->user_id,
            'version' => $this->version,
            'parent_component_id' => $this->parent_component_id,
        ]);

        $query->andFilterWhere(['like', 'position', $this->position]);

        return $dataProvider;
    }
}
