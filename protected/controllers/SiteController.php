<?php

class SiteController extends Controller
{
	/**
	 * Declares class-based actions.
	 */
	public function actions()
	{
		return array(
		// captcha action renders the CAPTCHA image displayed on the contact page
			'captcha'=>array(
				'class'=>'CCaptchaAction',
				'backColor'=>0xFFFFFF,
		),
		// page action renders "static" pages stored under 'protected/views/site/pages'
		// They can be accessed via: index.php?r=site/page&view=FileName
			'page'=>array(
				'class'=>'CViewAction',
		),
		);
	}

	public function filters()
	{
		return array(
            'accessControl',
		);
	}

	public function accessRules()
	{
		return array(
		array('deny',
                'actions'=>array('changePassword','inviteUser', 'registerGPSTracker'),
        		'users'=>array('?'),
		)
		);
	}


	/**
	 * This is the default 'index' action that is invoked
	 * when an action is not explicitly requested by users.
	 */
	public function actionIndex()
	{
		// renders the view file 'protected/views/site/index.php'
		// using the default layout 'protected/views/layouts/main.php'
		$this->render('index');
	}



	/**
	 * This is the action to handle external exceptions.
	 */
	public function actionError()
	{
		if($error=Yii::app()->errorHandler->error)
		{
			if(Yii::app()->request->isAjaxRequest)
			echo $error['message'];
			else
			$this->render('error', $error);
		}
	}

	/**
	 * Displays the contact page
	 */
	public function actionContact()
	{
		$model=new ContactForm;
		if(isset($_POST['ContactForm']))
		{
			$model->attributes=$_POST['ContactForm'];
			if($model->validate())
			{
				$headers="From: {$model->email}\r\nReply-To: {$model->email}";
				mail(Yii::app()->params['adminEmail'],$model->subject,$model->body,$headers);
				Yii::app()->user->setFlash('contact','Thank you for contacting us. We will respond to you as soon as possible.');
				$this->refresh();
			}
		}
		$this->render('contact',array('model'=>$model));
	}

	/**
	 * Displays the login page,
	 * If there is an error in validation or parameters it returns the form code with errors
	 * if everything is ok, it returns JSON with result=>1 and realname=>"..." parameters
	 * ATTENTION: This function is also used by mobile clients
	 */
	public function fbLogin($str)
	{
		$model = new LoginForm;
			
		
		$processOutput = true;
		
			//	echo print_r($str);
				//exit;
		// collect user input data
		if(isset($str))
		{
			$model->attributes = $str;
			// validate user input and if ok return json data and end application.
			
			if($model->validate() && $model->login()) {
				echo CJSON::encode(array(
								"result"=> "1",
								"id"=>Yii::app()->user->id,
								"realname"=> $model->getName(),
								"minDataSentInterval"=> Yii::app()->params->minDataSentInterval,
								"minDistanceInterval"=> Yii::app()->params->minDistanceInterval,
				));
				//Yii::app()->end();
			}
			if (Yii::app()->request->isAjaxRequest) {
				$processOutput = false;

			}
		}
	

		if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile')
		{

			if ($model->getError('password') != null) {
				$result = $model->getError('password');
			}
			else if ($model->getError('email') != null) {
				$result = $model->getError('email');
			}
			else if ($model->getError('rememberMe') != null) {
				$result = $model->getError('rememberMe');
			}

			echo CJSON::encode(array(
								"result"=> $result,
			));
			
			Yii::app()->end();
		}
		else {
			Yii::app()->clientScript->scriptMap['jquery.js'] = false;
			Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
			$this->renderPartial('login',array('model'=>$model), false, $processOutput);
		}
	}
	public function actionLogin()
	{
		$model = new LoginForm;
			
		$processOutput = true;
		
		// collect user input data
		if(isset($_REQUEST['LoginForm']))
		{
			$model->attributes = $_REQUEST['LoginForm'];
			// validate user input and if ok return json data and end application.
			
			if($model->validate() && $model->login()) {
				echo CJSON::encode(array(
								"result"=> "1",
								"id"=>Yii::app()->user->id,
								"realname"=> $model->getName(),
								"minDataSentInterval"=> Yii::app()->params->minDataSentInterval,
								"minDistanceInterval"=> Yii::app()->params->minDistanceInterval,
				));
				Yii::app()->end();
			}
			if (Yii::app()->request->isAjaxRequest) {
				$processOutput = false;

			}
		}
	

		if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile')
		{

			if ($model->getError('password') != null) {
				$result = $model->getError('password');
			}
			else if ($model->getError('email') != null) {
				$result = $model->getError('email');
			}
			else if ($model->getError('rememberMe') != null) {
				$result = $model->getError('rememberMe');
			}

			echo CJSON::encode(array(
								"result"=> $result,
			));
			
			Yii::app()->end();
		}
		else {
			Yii::app()->clientScript->scriptMap['jquery.js'] = false;
			Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
			$this->renderPartial('login',array('model'=>$model), false, $processOutput);
		}
	}
	/** 
	 * 
	 * facebook login action
	 */
	public function actionFacebooklogin() {
		Yii::import('ext.facebook.*');
	    $ui = new FacebookUserIdentity('370934372924974', 'c1e85ad2e617b480b69a8e14cfdd16c7');

		if ($ui->authenticate()) {
	        $user=Yii::app()->user;
	        $user->login($ui);
	
	       $this->FB_Web_Register($nd);
	        if($nd == 0)
	        {
	  
	   

				$str=array("email" => Yii::app()->session['facebook_user']['email'] ,"password" => Yii::app()->session['facebook_user']['id']) ;
				        	 
	        
	        	$this->fbLogin($str);
	          
	       
	        }else {
	        	
	        }
	    
	       
	         //exit;
	    	$this->redirect($user->returnUrl);
	 	} else {
	    	throw new CHttpException(401, $ui->error);
		}
	}

	/**
	 * Logs out the current user and redirect to homepage.
	 */
	public function actionLogout()
	{
		Yii::app()->user->logout();
		if (isset($_REQUEST['client']) && $_REQUEST['client'] == 'mobile') {
			// if mobile client end the app, no need to redirect...
			echo CJSON::encode(array(
								"result"=> "1"));
			Yii::app()->end();
		}
		else {
			$this->redirect(Yii::app()->homeUrl);
		}
	}
	
	
	/**
	 * Changes the user's current password with the new one
	 */
	public function actionChangePassword()
	{
		$model = new ChangePasswordForm;

		$processOutput = true;
		// collect user input data
		if(isset($_POST['ChangePasswordForm']))
		{
			$model->attributes=$_POST['ChangePasswordForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {
				//$users=Users::model()->findByPk(Yii::app()->user->id);
				//$users->password=md5($model->newPassword);

				//if($users->save()) // save the change to database
				if(Users::model()->changePassword(Yii::app()->user->id, $model->newPassword)) // save the change to database
				{
					echo CJSON::encode(array("result"=> "1"));
				}
				else
				{
					echo CJSON::encode(array("result"=> "0"));
				}
				Yii::app()->end();
			}

			if (Yii::app()->request->isAjaxRequest) {
				$processOutput = false;

			}
		}

		Yii::app()->clientScript->scriptMap['jquery.js'] = false;
		Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;

		$this->renderPartial('changePassword',array('model'=>$model), false, $processOutput);
	}

	public function actionRegister()
	{
		$model = new RegisterForm;

		$processOutput = true;
		$isMobileClient = false;
		// collect user input data
		if(isset($_POST['RegisterForm']))
		{
			$isMobileClient = false;
			if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile') {
				$isMobileClient = true;
			}
			$model->attributes = $_POST['RegisterForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {

				$time = date('Y-m-d h:i:s');

				$userCandidates = new UserCandidates;
				$userCandidates->email = $model->email;
				$userCandidates->realname = $model->name;
				$userCandidates->password = md5($model->password);
				$userCandidates->time = $time;

				if($userCandidates->save()) // save the change to database
				{
					$key = md5($model->email.$time);
					$message = 'Hi '.$model->name.',<br/> <a href="http://'.Yii::app()->request->getServerName() . $this->createUrl('site/activate',array('email'=>$model->email,'key'=>$key)).'">'.
					'Click here to register to traceper</a> <br/>';										
					$message .= '<br/> Your Password is :'.$model->password;
					$message .= '<br/> The Traceper Team';
					$headers  = 'MIME-Version: 1.0' . "\r\n";
					$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
					$headers  .= 'From: '. Yii::app()->params->contactEmail .'' . "\r\n";
					//echo $message;
					mail($model->email, "Traceper Activation", $message, $headers);
					echo CJSON::encode(array("result"=> "1"));
				}
				else
				{
					echo CJSON::encode(array("result"=> "Unknown error"));
				}
				Yii::app()->end();
			}

			if (Yii::app()->request->isAjaxRequest) {
				$processOutput = false;

			}
		}

		if ($isMobileClient == true)
		{
			if ($model->getError('password') != null) {
				$result = $model->getError('password');
			}
			else if ($model->getError('email') != null) {
				$result = $model->getError('email');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}

			echo CJSON::encode(array(
								"result"=> $result,
			));
			Yii::app()->end();
		}
		else {
			Yii::app()->clientScript->scriptMap['jquery.js'] = false;
			Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;

			$this->renderPartial('register',array('model'=>$model), false, $processOutput);
		}

	}

	public function actionFB_M_Register()
	{
		$model = new RegisterForm;

		$processOutput = true;
		$isMobileClient = false;
		// collect user input data
		if(isset($_POST['RegisterForm']))
		{
			$isMobileClient = false;
			if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile') {
				$isMobileClient = true;
			}
			$model->attributes = $_POST['RegisterForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {

				$time = date('Y-m-d h:i:s');

				$users = new Users;
				$users->email = $model->email;
				$users->realname = $model->name;
				$users->password = md5($model->password);
				$users->account_type = $model->account_type;
				$users->fb_id = $model->ac_id;
				$result = "Unknown error";
				
			if($users->save())
				{
					
					echo CJSON::encode(array("result"=> "9"));
					//echo CJSON::encode(array("result"=> "1"));
				}
				else
				{
					echo CJSON::encode(array("result"=> "Unknown error"));
				}
				Yii::app()->end();
			}

			if (Yii::app()->request->isAjaxRequest) {
				$processOutput = false;

			}
		}

		if ($isMobileClient == true)
		{
			if ($model->getError('password') != null) {
				$result = $model->getError('password');
			}
			else if ($model->getError('email') != null) {
				$result = $model->getError('email');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}

			echo CJSON::encode(array(
								"result"=> $result,
			));
			Yii::app()->end();
		}
		else {
			Yii::app()->clientScript->scriptMap['jquery.js'] = false;
			Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;

			$this->renderPartial('register',array('model'=>$model), false, $processOutput);
		}

	}

	public function actionGP_M_Register()
	{
		$model = new RegisterForm;

		$processOutput = true;
		$isMobileClient = false;
		// collect user input data
		if(isset($_POST['RegisterForm']))
		{
			$isMobileClient = false;
			if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile') {
				$isMobileClient = true;
			}
			$model->attributes = $_POST['RegisterForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {

				$time = date('Y-m-d h:i:s');

				$users = new Users;
				$users->email = $model->email;
				$users->realname = $model->name;
				$users->password = md5($model->password);
				$users->gp_image = substr($model->image,0,strlen($model->image)-6);
				$users->account_type = $model->account_type;
				$users->g_id = $model->ac_id;
				$result = "Unknown error";
				
			if($users->save())
				{
					
					echo CJSON::encode(array("result"=> "9"));
					//echo CJSON::encode(array("result"=> "1"));
				}
				else
				{
					echo CJSON::encode(array("result"=> "Unknown error"));
				}
				Yii::app()->end();
			}

			if (Yii::app()->request->isAjaxRequest) {
				$processOutput = false;

			}
		}

		if ($isMobileClient == true)
		{
			if ($model->getError('password') != null) {
				$result = $model->getError('password');
			}
			else if ($model->getError('email') != null) {
				$result = $model->getError('email');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}

			echo CJSON::encode(array(
								"result"=> $result,
			));
			Yii::app()->end();
		}
		else {
			Yii::app()->clientScript->scriptMap['jquery.js'] = false;
			Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;

			$this->renderPartial('register',array('model'=>$model), false, $processOutput);
		}

	}	
	
	//facebook web register
	public function FB_Web_Register()
	{

		
			
		$result = 0;
			
			// validate user input and if ok return json data and end application.
			if(Yii::app()->session['facebook_user']) {

				$time = date('Y-m-d h:i:s');
				$users = new Users;
				$users->email = Yii::app()->session['facebook_user']['email'];
				$users->realname = Yii::app()->session['facebook_user']['name'];
				$users->password = md5(Yii::app()->session['facebook_user']['id']);
				$users->account_type = 1;
				$users->fb_id = Yii::app()->session['facebook_user']['id'];
				$result = "Unknown error";
	
				
				try
				{
				$users->save();	
				$result = 1;	
				}catch (Exception $e) 
				{
				$result = 0; 	
				}
			
			
				
				
			
			}
			return $result;

	}
	
	
	public function actionRegisterGPSTracker()
	{
		$model = new RegisterGPSTrackerForm;

		$processOutput = true;
		$isMobileClient = false;
		// collect user input data
		if(isset($_POST['RegisterGPSTrackerForm']))
		{
			$isMobileClient = false;
			if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile') {
				$isMobileClient = true;
			}
			$model->attributes = $_POST['RegisterGPSTrackerForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {
				
				//Check whether a device exists with the same name in the Users table (Since the table 'Users' is used as common for both 
				//real users and devices we cannot add unique index for realname, so we have to check same name existance manually)
				if(Users::model()->find('gender=:gender AND realname=:name', array(':gender'=>'device', ':name'=>$model->name)) == null)
				{
					$users = new Users;
					$users->realname = $model->name;
					$users->deviceId = $model->deviceId;
					
					//For database recording, because of email and password are required fields
					$users->email = $model->deviceId;
					$users->password = md5($model->name);
					$users->gender = 'device';
					
					try
					{
						if($users->save()) // save the change to database
						{
							$friend = new Friends();
							$friend->friend1 = Yii::app()->user->id;
							$friend->friend1Visibility = 1; //default visibility setting is visible
							$friend->friend2 = $users->getPrimaryKey();
							$friend->friend2Visibility = 1; //default visibility setting is visible
							$friend->status = 1;
					
							if ($friend->save())
							{
								echo CJSON::encode(array("result"=> "1"));
							}
							else
							{
								echo CJSON::encode(array("result"=> "Unknown error"));
							}
						}
						else
						{
							echo CJSON::encode(array("result"=> "Unknown error"));
						}
					}
					catch (Exception $e)
					{
						if($e->getCode() == Yii::app()->params->duplicateEntryDbExceptionCode) //Duplicate Entry
						{
							echo CJSON::encode(array("result"=> "Duplicate Entry"));
						}
						Yii::app()->end();
							
						//					echo 'Caught exception: ',  $e->getMessage(), "\n";
						//    				echo 'Code: ', $e->getCode(), "\n";
					}									
				}
				else
				{
					echo CJSON::encode(array("result"=> "Duplicate Name"));
				}
				
				Yii::app()->end();
			}
				
			if (Yii::app()->request->isAjaxRequest) {
				$processOutput = false;

			}
		}

		if ($isMobileClient == true)
		{
			if ($model->getError('password') != null) {
				$result = $model->getError('password');
			}
			else if ($model->getError('email') != null) {
				$result = $model->getError('email');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}

			echo CJSON::encode(array(
								"result"=> $result,
			));
			Yii::app()->end();
		}
		else {
			Yii::app()->clientScript->scriptMap['jquery.js'] = false;
			Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
			$this->renderPartial('registerGPSTracker',array('model'=>$model), false, $processOutput);
		}

	}
	
	
	public function actionRegisterNewStaff()
	{
		$model = new RegisterNewStaffForm;
	
		$processOutput = true;
		$isMobileClient = false;
		// collect user input data
		if(isset($_POST['RegisterNewStaffForm']))
		{
			$isMobileClient = false;
			if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile') {
				$isMobileClient = true;
			}
			$model->attributes = $_POST['RegisterNewStaffForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {
	
// 				if(Users::model()->find('email=:email', array(':email'=>'email')) == null)
// 				{
// 					$users = new Users;
// 					$users->realname = $model->name;
// 					$users->email = $model->email;
// 					$users->password = md5($model->password);
// 					$users->gender = 'staff';	

					try
					{
						//if($users->save()) // save the change to database
						if(Users::model()->saveUser($model->email, $model->password, $model->realname, "staff"))
						{
							$friend = new Friends();
							$friend->friend1 = Yii::app()->user->id;
							$friend->friend1Visibility = 1; //default visibility setting is visible
							$friend->friend2 = $users->getPrimaryKey();
							$friend->friend2Visibility = 1; //default visibility setting is visible
							$friend->status = 1;
								
							//if ($friend->save())
							if(Friends::model()->makeFriends(Yii::app()->user->id, Users::model()->getUserId($model->email)))
							{
								echo CJSON::encode(array("result"=> "1"));
							}
							else
							{
								echo CJSON::encode(array("result"=> "Unknown error"));
							}
						}
						else
						{
							echo CJSON::encode(array("result"=> "Unknown error"));
						}
					}
					catch (Exception $e)
					{
						if($e->getCode() == Yii::app()->params->duplicateEntryDbExceptionCode) //Duplicate Entry
						{
							echo CJSON::encode(array("result"=> "Duplicate Entry"));
						}
						Yii::app()->end();
							
						//					echo 'Caught exception: ',  $e->getMessage(), "\n";
						//    				echo 'Code: ', $e->getCode(), "\n";
					}
// 				}
// 				else
// 				{
// 					echo CJSON::encode(array("result"=> "Duplicate Name"));
// 				}
	
				Yii::app()->end();
			}
	
			if (Yii::app()->request->isAjaxRequest) {
				$processOutput = false;
	
			}
		}
	
		if ($isMobileClient == true)
		{
			if ($model->getError('password') != null) {
				$result = $model->getError('password');
			}
			else if ($model->getError('email') != null) {
				$result = $model->getError('email');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}
			else if ($model->getError('passwordAgain') != null) {
				$result = $model->getError('passwordAgain');
			}
	
			echo CJSON::encode(array(
					"result"=> $result,
			));
			Yii::app()->end();
		}
		else {
			Yii::app()->clientScript->scriptMap['jquery.js'] = false;
			Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;
			$this->renderPartial('registerNewStaff',array('model'=>$model), false, $processOutput);
		}
	
	}	

	public function actionInviteUsers()
	{
		$model = new InviteUsersForm;

		$processOutput = true;
		// collect user input data
		if(isset($_POST['InviteUsersForm']))
		{
			$model->attributes = $_POST['InviteUsersForm'];
			// validate user input and if ok return json data and end application.
			if($model->validate()) {

				$emailArray= $this->splitEmails($model->emails);
				$arrayLength = count($emailArray);
				$invitationSentCount = 0;
				for ($i = 0; $i < $arrayLength; $i++)
				{
					$dt = date("Y-m-d H:m:s");

					$invitedUsers = new InvitedUsers;
					$invitedUsers->email = $emailArray[$i];
					$invitedUsers->dt = $dt;

					if ($invitedUsers->save())
					{
						$key = md5($emailArray[$i].$dt);
						//send invitation mail
						$invitationSentCount++;

						//Invitation kontrol� yap�ld���nda bu k�s�m a��lacak

						//$message = 'Hi ,<br/> You have been invited to traceper by one of your friends <a href="'.$this->createUrl('site/register',array('invitation'=>true, 'email'=>$emailArray[$i],'key'=>$key)).'">'.
						//'Click here to register to traceper</a> <br/>';

						$message = 'Hi ,<br/> You have been invited to traceper by one of your friends <a href="'.$this->createUrl('site/register').'">'.
						'Click here to register to traceper</a> <br/>';
						$message .= '<br/> ' . $model->message;
						$message .= '<br/> The Traceper Team';
						$headers  = 'MIME-Version: 1.0' . "\r\n";
						$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
						$headers  .= 'From: contact@traceper.com' . "\r\n";
						//echo $message;
						mail($emailArray[$i], "Traceper Invitation", $message, $headers);
					}
				}

				if ($arrayLength == $invitationSentCount) // save the change to database
				{
					echo CJSON::encode(array("result"=> "1"));
				}
				else
				{
					echo CJSON::encode(array("result"=> "0"));
				}
				Yii::app()->end();
			}

			if (Yii::app()->request->isAjaxRequest) {
				$processOutput = false;

			}
		}

		Yii::app()->clientScript->scriptMap['jquery.js'] = false;
		Yii::app()->clientScript->scriptMap['jquery-ui.min.js'] = false;

		$this->renderPartial('inviteUsers',array('model'=>$model), false, $processOutput);
	}

	private function splitEmails($emails)
	{
		$emails = str_replace(array(" ",",","\r","\n"),array(";",";",";",";"),$emails);
		$emails = str_replace(";;", ";",$emails);
		$emails = explode(";", $emails);
		return $emails;
	}

	public function actionActivate()
	{
		$result = "Sorry, you entered this page with wrong parameters";
		if (isset($_GET['email']) && $_GET['email'] != null
			&& isset($_GET['key']) && $_GET['key'] != null
			)
		{
			$email = $_GET['email'];
			$key = $_GET['key'];

			$processOutput = true;
			// collect user input data

			$criteria=new CDbCriteria;
			$criteria->select='Id,email,realname,password,time';
			$criteria->condition='email=:email';
			$criteria->params=array(':email'=>$email);
			$userCandidate = UserCandidates::model()->find($criteria); // $params is not needed

			$generatedKey =  md5($email.$userCandidate->time);
			if ($generatedKey == $key)
			{
				$result = "Sorry, there is a problem in activating the user";
				if(Users::model()->saveUser($userCandidate->email, $userCandidate->password, $userCandidate->realname))
				{
					$userCandidate->delete();
					$result = "Your account has been activated successfully, you can login now";
					//echo CJSON::encode(array("result"=> "1"));
				}
			}
		}

		$this->renderPartial('accountActivationResult',array('result'=>$result), false, true);
	}
}


