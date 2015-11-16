# yii-mongo-record
Yii1 MongoDB ActiveRecord model implementation

# Installation

Via composer: 

```
require: {
    "edwardstock/yii-mongo-record": "dev-master"
}
```

# Yii configuration

```
'mongodb' => [
    'class'    => 'common.extensions.Mongo.Connection.MongoDbConnection',
    'host'     => 'localhost',
    'user'     => 'MongoUser',
    'password' => 'MongoPassword',
    'db'       => 'Collection name',
    'port'     => 27017
],
```

If you have anonymous connection, use next config: 
  
  
```
'mongodb' => [
    'class'    => 'common.extensions.Mongo.Connection.MongoDbConnection',
    'host'     => 'localhost',
    'db'       => 'Collection name',
],
```


<br/>
For more configuration. see [MongoDbConnection](https://github.com/edwardstock/yii-mongo-record/wiki/MongoDbConnection)

# Basic example

```
<?php

class User extends MongoRecord {
    
    public $username;
    public $password;
    public $role;
    
    /**
     * 
     * @return User
     */
    public static function model($className = __CLASS__) {
        return parent::model($className);
    }
    
    public function attributes() {
        return [
            'username',
            'password',
            'role',
        ];
    }
    
    public function rules() {
        return [
            ['username, password', 'required'],
            ['role', 'type', 'type'=>'string'],
        ];
    }
    
    public function beforeSave() {
        
        if($this->isNewRecord) {
            $this->password = Enctyption::passwordHash($this->password);
        }
    
        return parent::beforeSave();
    }

    
}


//creating new user
$user = new User();
$user->username = 'superman';
$user->password = 'myPassword';
if($user->validate()) {
    $user->save();
}

//here u can use id of user by:
$userId = $user->id;


// finding user
$userId = '5644541785992b6c708b4567';
$user = User::model()->findById($userId);

//or u can use MongoId
$user = User::model()->findById(new MongoId($userId));


//deleting
$user->delete();
?>
```