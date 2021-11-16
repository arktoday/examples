<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "user_access".
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $table
 * @property string|null $column
 * @property string|null $value
 *
 * @property User $user
 */
class UserAccess extends \yii\db\ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'user_access';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['user_id'], 'integer'],
            [['table', 'column'], 'string', 'max' => 64],
            [['value'], 'string', 'max' => 255],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'table' => 'Table',
            'column' => 'Column',
            'value' => 'Value',
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser() {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }
}
