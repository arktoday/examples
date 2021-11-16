<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "work_position".
 *
 * @property int $id
 * @property int|null $sort
 * @property int|null $depth_level
 * @property string|null $name
 *
 * @property User[] $users
 */
class WorkPosition extends \yii\db\ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'work_position';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['sort', 'depth_level'], 'integer'],
            [['name'], 'string', 'max' => 255],
            ['name', 'unique']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'id' => 'ID',
            'sort' => 'Порядок (где 1 - Руководитель ...)',
            'name' => 'Должность',
            'depth_level' => 'Права',
        ];
    }

    /**
     * Gets query for [[Users]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUsers() {
        return $this->hasMany(User::className(), ['work_position_id' => 'id']);
    }
}
