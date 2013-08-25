<?php

class UsersController extends Controller
{
	
	private $dataFetchedTimeKey = "UsersController.dataFetchedTime";
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
						'actions'=>array('addAsFriend', '',
								'deleteFriendShip','getFriendRequestList',
								'getUserPastPointsXML', 'getUserListXML', 'search',
								'takeMyLocation', 'getUserInfo',
								'getUserListJson'),
						'users'=>array('?'),
				)
		);
	}
	
	private function calculateDistance($lat1, $lon1, $lat2, $lon2, $unit = "K") {
		$theta = $lon1 - $lon2;
		$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
		$dist = acos($dist);
		$dist = rad2deg($dist);
		$miles = $dist * 60 * 1.1515;
		$unit = strtoupper($unit);
		
		$distance = 0;
	
		if($unit == "K")
		{
			$distance = ($miles * 1.609344);
		}
		elseif($unit == "N") {
			$distance = ($miles * 0.8684);
		}
		else
		{
			$distance = $miles;
		}
		
		return $distance;
	}	


	/*
	 * this action is used by mobile clients
	*/
	public function actionTakeMyLocation()
	{
		$result = null;
		
		if (isset($_REQUEST['latitude']) && $_REQUEST['latitude'] != NULL
				&& isset($_REQUEST['longitude']) && $_REQUEST['longitude'] != NULL
				&& isset($_REQUEST['altitude']) && $_REQUEST['altitude'] != NULL
				&& isset($_REQUEST['deviceId']) && $_REQUEST['deviceId'] != NULL
				&& isset($_REQUEST['time']) && $_REQUEST['time'] != NULL
		)
		{
			$latitude = (float) $_REQUEST['latitude'];
			$longitude = (float) $_REQUEST['longitude'];
			$altitude = (float) $_REQUEST['altitude'];
			$deviceId = $_REQUEST['deviceId'];
			$calculatedTime = date('Y-m-d H:i:s',  $_REQUEST['time']);
			$address = null;
			
			//Adres bilgisi opsiyonel. Geliyorsa al, gelmiyorsa null de�er ata
			if(isset($_REQUEST['address']) && $_REQUEST['address'] != NULL)
			{
				$address = $_REQUEST['address'];
			}
			else 
			{
				$address = null;
			}

			if (Yii::app()->user->id != false)
			{
				$lastLatitude = 0;
				$lastLongitude = 0; 
				$lastAltitude = 0; 
				$minDistanceInterval = 0;
				$minDataSentInterval = 0;

				Users::model()->getMinimumIntervalValues(Yii::app()->user->id, $minDistanceInterval, $minDataSentInterval);
				UserWasHere::model()->getMostRecentLocation(Yii::app()->user->id, $lastLatitude, $lastLongitude, $lastAltitude);
				
				$distanceInKms = $this->calculateDistance($lastLatitude, $lastLongitude, $latitude, $longitude);
				$distanceInMs = $distanceInKms * 1000;
				
				//Users table is always updated whether the distance difference is high enough or not
				if (Users::model()->updateLocation($latitude, $longitude, $altitude, $address, $calculatedTime, Yii::app()->user->id) == 1)
				{
					$result = "1"; //Location updated successfully
				}
				else
				{
					$result = "0"; //Error occured in save operation
				}

				//If the distance difference is greater than minDistanceInterval, add a new record to UserWasHere table 
				if($distanceInMs > $minDistanceInterval)
				{
					//Fb::warn('if($distanceInMs > $minDistanceInterval)', "UsersController");

					if(UserWasHere::model()->logLocation(Yii::app()->user->id, $latitude, $longitude, $altitude, $deviceId, $calculatedTime))
					{
						//Fb::warn('UserWasHere::model()->logLocation() successful', "UsersController");
						
						//$result = "1"; //Values updated successfully
					}
					else
					{
						//Fb::warn('UserWasHere::model()->logLocation() ERROR', "UsersController");
						
						//$result = "0"; //Error occured in save operation
					}					
				}				
			}
			else
			{
				$result = "-1"; //No valid user Id
			}
		}
		else
		{
			$result = "-2"; //Missing Parameter
		}
		
		$resultArray = array("result"=>$result);
		
		if($result == "1") {
			$resultArray = array_merge($resultArray, array(
					"minDataSentInterval"=>$minDataSentInterval,
					"minDistanceInterval"=>$minDistanceInterval,
			));
		}
		
		echo CJSON::encode(
				$resultArray
		);
		//$this->redirect(array('geofence/checkGeofenceBoundaries', 'friendId' => Yii::app()->user->id, 'friendLatitude' => $latitude, 'friendLongitude' => $longitude));
		Yii::app()->end();
	}
		
	/*
	 * this action is used by mobile clients
	*/
	public function actionUpdateProfile()
	{
		$result = null;
		
		$realname =  null;
		$password = null;
		$gender =  null;		
		$minDataSentInterval = null;
		$minDistanceInterval = null;
		$autoSend = null;

		$atLeastOneItemExists = false;
		
		if (isset($_REQUEST['realname']) && $_REQUEST['realname'] != NULL)
		{
			$realname = $_REQUEST['realname'];
			$atLeastOneItemExists = true;
		}
		
		if (isset($_REQUEST['password']) && $_REQUEST['password'] != NULL)
		{
			$password = $_REQUEST['password'];
			$atLeastOneItemExists = true;
		}

		if (isset($_REQUEST['gender']) && $_REQUEST['gender'] != NULL)
		{
			$gender = $_REQUEST['gender'];
			$atLeastOneItemExists = true;
		}		
				
		if (isset($_REQUEST['minDataSentInterval']) && $_REQUEST['minDataSentInterval'] != NULL)
		{
			$minDataSentInterval = $_REQUEST['minDataSentInterval'];
			$atLeastOneItemExists = true;
		}

		if (isset($_REQUEST['minDistanceInterval']) && $_REQUEST['minDistanceInterval'] != NULL)
		{
			$minDistanceInterval = $_REQUEST['minDistanceInterval'];
			$atLeastOneItemExists = true;
		}	

		if (isset($_REQUEST['autoSend']) && $_REQUEST['autoSend'] != NULL)
		{
			$autoSend = $_REQUEST['autoSend'];
			$atLeastOneItemExists = true;
		}	

		if(true == $atLeastOneItemExists)
		{			
			if (Yii::app()->user->id != false)
			{
				if(Users::model()->updateProfileItemsNotNull(Yii::app()->user->id, $realname, $password, $gender, $minDataSentInterval, $minDistanceInterval, $autoSend))
				{
					$result = "1"; //Not null values saved successfully
					
					//Fb::warn("Not null values saved successfully", "UsersController");
				}
				else
				{
					$result = "0"; //Error occured in save operation
				}					
			}
			else
			{
				$result = "-1"; //No valid user Id
			}
		}
		else
		{
			$result = "-2"; //There is not any parameter which is not null
		}

		echo CJSON::encode(array(
				"result"=>$result,
		));		
		
		Yii::app()->end();
	}	
		
	public function actionGetFriendList()
	{
		$userType = array();

		if (isset($_GET['userType']) && $_GET['userType'] != NULL)
		{
			$userType = $_GET['userType'];
		}

		if(Yii::app()->user->id != null)
		{
			$dataProvider = Users::model()->getListDataProvider($this->getFriendIdList(), $userType);
		}
		else
		{
			$dataProvider = null;
		}

		Yii::app()->clientScript->scriptMap['jquery.js'] = false;

		if(($userType == UserType::RealStaff) || ($userType == UserType::GPSStaff))
		{
			Yii::app()->clientScript->scriptMap['jquery.yiigridview.js'] = false;
		}
		$this->renderPartial('usersInfo',array('dataProvider'=>$dataProvider,'model'=>new SearchForm(), 'userType'=>$userType), false, true);
	}

	private function unsetFriendIdList() {
		unset(Yii::app()->session['friendList']);
	}

	private function getFriendCount() {
		if (isset(Yii::app()->session['friendList'])== false) {
			$this->getFriendIdList();
		}

		return Yii::app()->session['friendCount'];
	}

	private function getFriendArray() {
		if (isset(Yii::app()->session['friendList'])== false) {
			$this->getFriendIdList();
		}

		return Yii::app()->session['friendArray'];
	}


	private function getFriendIdList() {
		//if (isset(Yii::app()->session['friendList']) == false) {
		if (true) {
			$friendsResult = Users::model()->getFriendList(Yii::app()->user->id);
			$length = count($friendsResult);
			Yii::app()->session['friendCount'] = $length;
			$friends = array();
			for ($i = 0; $i < $length; $i++) {
				array_push($friends, $friendsResult[$i]['friend']);
			}
			$result = -1;
			if (count($friends) > 0) {
				$result = implode(',', $friends);
			}
			Yii::app()->session['friendArray'] = $friends;
			Yii::app()->session['friendList'] = $result;
		}		
		 
		return Yii::app()->session['friendList'];
	}

	public function actionGetUserInfoJSON()
	{
		$out = "Missing parameter";
		if (isset($_REQUEST['userId']) && $_REQUEST['userId'] > 0) {

			$userId = (int) $_REQUEST['userId'];
			$friendArray = $this->getFriendArray();
			$out = "No permission to get this user location";
			
			if ($userId == Yii::app()->user->id || array_search($userId,$friendArray) !== false)
			{
				$dataProvider = Users::model()->getListDataProvider($userId, null, null, null, 0, 1, 1);
				$out = $this->prepareJson($dataProvider);
			}
		}

		echo $out;
		Yii::app()->end();
	}
	/**
	 * this is intented to be used by mobile app
	 * Enter description here ...
	 */
	public function actionGetUserListJson()
	{	
		$pageNo = 1;
		$userTypes = array();

		if (isset($_REQUEST['pageNo']) && $_REQUEST['pageNo'] > 0) {
			$pageNo = (int)$_REQUEST['pageNo'];
		}
		
		if (isset($_REQUEST['userType'])) {
			$userTypes[] = (int)$_REQUEST['userType'];
			
			//Fb::warn("userType is SET", "actionGetUserListJson()");
		}

		$offset = ($pageNo - 1) * Yii::app()->params->itemCountInDataListPage;
		
		//Webde kullan�c�n�n kendi ismine t�klad���nda kendini konumunu g�rebilmesi i�in
		$friendCount = $this->getFriendCount() + 1; // +1 is for herself
		//$friendCount = $this->getFriendCount();
			
		$friendIdList = $this->getFriendIdList();
		
		if (isset($_REQUEST['client']) && $_REQUEST['client']=='mobile')
		{
			//Do not add user himself for mobile
		}
		else //For web
		{
			if ($friendIdList != -1) {
				$friendIdList .= ',' . Yii::app()->user->id;
			}
			else {
				$friendIdList = Yii::app()->user->id;
			}			
		}
				
		$time = null;
		
		if (isset($_REQUEST['list']) && $_REQUEST['list'] == "onlyUpdated") {
			$time = Yii::app()->session[$this->dataFetchedTimeKey];
		}

		$newFriendId = null;
		
		if (isset($_REQUEST['newFriendId'])) {
			$newFriendId = (int)$_REQUEST['newFriendId'];
		}		

		//Fb::warn("actionGetUserListJson() called", "UsersController");

		$dataProvider = Users::model()->getListDataProvider($friendIdList, $userTypes, $newFriendId,  $time, $offset, Yii::app()->params->itemCountInDataListPage, $friendCount);
		
		$out = $this->prepareJson($dataProvider);	

		echo $out;
		Yii::app()->session[$this->dataFetchedTimeKey] = time();
		Yii::app()->end();

	}

	public function actionGetUserPastPointsJSON(){

		if (isset($_REQUEST['userId']))
		{
			$userId = (int) $_REQUEST['userId'];
			$pageNo = 1;
			if (isset($_REQUEST['pageNo']) && $_REQUEST['pageNo'] > 0) {
				$pageNo = (int) $_REQUEST['pageNo'];
			}
			
			$offset = ($pageNo - 1) * Yii::app()->params->itemCountInDataListPage;
			$offset++;  // to not get the last location

			$dataProvider = UserWasHere::model()->getPastPointsDataProvider($userId, $pageNo, Yii::app()->params->itemCountInDataListPage);
				
			$out = $this->preparePastPointsJson($dataProvider);
		}
		echo $out;
	}
	
	public function actionSearch() {
		$model = new SearchForm();

		$dataProvider = null;
		if(isset($_REQUEST['SearchForm']))
		{
			$model->attributes = $_REQUEST['SearchForm'];
			if ($model->validate()) {
				$dataProvider = Users::model()->getSearchUserDataProvider(null, $model->keyword, "SearchForm[keyword]");
			}
		}
		Yii::app()->clientScript->scriptMap['jquery.js'] = false;
		Yii::app()->clientScript->scriptMap['jquery.yiigridview.js'] = false;
		$this->renderPartial('searchResults',array('model'=>$model, 'dataProvider'=>$dataProvider), false, true);
	}

	public function actionSearchJSON() {
		$model = new SearchForm();
		$result = null;
		$dataProvider = null;
		
		if(isset($_REQUEST['SearchForm']))
		{
			$model->attributes = $_REQUEST['SearchForm'];
			if ($model->validate()) {

				$dataProvider = Users::model()->getSearchUserDataProvider(null, $model->keyword, "SearchForm[keyword]");
				$out = $this->prepareSearchUserResultJson($dataProvider);
			}
			else
			{
				$result = "-1"; //Model invalid
				$out = '{"result":"'.$result.'"}';
			}
		}
		else
		{
			$result = "-2"; //Search form not set
			$out = '{"result":"'.$result.'"}';			
		}
		
		echo $out;
		Yii::app()->end();
	}
	public function actionDeleteFriendShip(){
		//$result = 'Missing Data';
		if (isset($_REQUEST['friendId']))
		{
			$friendId = (int) $_REQUEST['friendId'];
			$friendShipStatus = -1;

			$actionResult = Friends::model()->deleteFriendShip($friendId, $friendShipStatus);
			
			if ($actionResult == 1) {
				$this->unsetFriendIdList();
			}
		}

		echo CJSON::encode(array(
				"result"=>$actionResult,
				"friendShipStatus"=>$friendShipStatus,
				"deletedFriendId"=>$friendId
		));
	}

	public function actionDeleteUser(){
		$result = 'Missing Data';
		if (isset($_REQUEST['userId']))
		{
			$userId = (int) $_REQUEST['userId'];

			$result = 'Error occured';
			if (Users::model()->deleteUser($userId)){
				$result = 1;
				$this->unsetFriendIdList();
			}
		}

		echo CJSON::encode(array(
				"result"=>$result,
		));

	}

	public function actionGetFriendRequestList(){

		// we look at the friend2 field because requester id is stored in friend1 field
		// and only friend who has been requested to be a friend can approve frienship

		Friends::model()->updateAll(array('isNew' => 0), 'friend2 = '.Yii::app()->user->id.' AND status = 0 AND isNew = 1');
		$dataProvider = Friends::model()->getFriendRequestDataProvider(Yii::app()->user->id, Yii::app()->params->itemCountInOnePage);
			
		Yii::app()->clientScript->scriptMap['jquery.js'] = false;
		Yii::app()->clientScript->scriptMap['jquery.yiigridview.js'] = false;
		
		//Complete solution for blinking problem at FireFox
		if (Yii::app()->request->getIsAjaxRequest()) {
			Yii::app()->clientScript->scriptMap['*.js'] = false;
			Yii::app()->clientScript->scriptMap['*.css'] = false;
		}
				
		$this->renderPartial('userListDialog',array('dataProvider'=>$dataProvider), false, true);
	}
	
	
	public function actionGetFriendRequestListJson(){		
		
		$dataProvider = Friends::model()->getFriendRequestDataProvider(Yii::app()->user->id, Yii::app()->params->itemCountInOnePage);
		
		$out = $this->prepareSearchUserResultJson($dataProvider);
		//$out = $this-> prepareJson($sql, "userSearch" ,$userId);
		echo $out;
		Yii::app()->end();
	}
	
	public function actionApproveFriendShip(){
		$result = 'Missing Data';
// 		if (isset($_REQUEST['friendShipId']))
// 		{
// 			$friendShipId = (int) $_REQUEST['friendShipId'];
// 			// only friend2 can approve friendship because friend1 makes the request
// 			$friendShip = Friends::model()->findByPk($friendShipId, array('condition'=>'friend2=:friend2',
// 					'params'=>array(':friend2'=>Yii::app()->user->id,
// 					),
// 			)
// 			);
// 			$result = 'Error occured';
// 			if (Friends::model()->approveFriendShip($friendShipId, $userId) == true)
// 			{
// 				$result = 1;
// 				$this->unsetFriendIdList();
// 			}			
// 		}
		
		if (isset($_REQUEST['friendId']))
		{
			$friendId = (int) $_REQUEST['friendId'];
			// only friend2 can approve friendship because friend1 makes the request
			//$friendShip = Friends::model()->find('friend1=:friend1 AND friend2=:friend2', array(':friend1'=>$friendId, ':friend2'=>Yii::app()->user->id));
			
			$result = 'Error occured';			
			if (Friends::model()->approveFriendShip($friendId, Yii::app()->user->id) == true)
			{
				$result = 1;
				$this->unsetFriendIdList();
			}
		}		
		echo CJSON::encode(array(
				"result"=>$result,
				"friendId"=>$friendId,
		));

	}

	public function actionAddAsFriend()
	{
		$result = "-100";

		if(isset($_REQUEST['friendId']) && isset($_REQUEST['language'])) 
		{
			$friendId = (int)$_REQUEST['friendId'];
			
			$mobileLang = null;
			$mobileLang = $_REQUEST['language'];
						
			$result = Friends::model()->addAsFriend(Yii::app()->user->id, $friendId);
			
			//Friends tablosuna ekleme başarılıysa mail at, yoksa boşuna mail atma
			if(1 == $result)
			{
				$requesterName = null;
				$requesterEmail = null;
				Users::model()->getUserInfo(Yii::app()->user->id, $requesterName, $requesterEmail);
					
				$friendCandidateName = null;
				$friendCandidateEmail = null;
				Users::model()->getUserInfo($friendId, $friendCandidateName, $friendCandidateEmail);
					
				$isTranslationRequired = false;
					
				if($mobileLang != null)
				{
					if($mobileLang == 'tr')
					{
						if(Yii::app()->language == 'tr')
						{
							$isTranslationRequired = false;
						}
						else
						{
							$isTranslationRequired = true;
						}
					}
					else
					{
						if(Yii::app()->language == 'tr')
						{
							$isTranslationRequired = true;
						}
						else
						{
							$isTranslationRequired = false;
						}
					}
				}
					
				if($isTranslationRequired == true)
				{
					if($mobileLang == 'tr')
					{
						Yii::app()->language = 'tr';
					}
					else
					{
						Yii::app()->language = 'en';
					}
				}
				
				$message = Yii::t('site', 'Hi').' '.$friendCandidateName.',<br/><br/>';
				$message .= $requesterName.', ';
				$message .= Yii::t('users', 'wants to be your friend at Traceper').'.'.'<br/><br/>';
				$message .= Yii::t('users', 'If you wish you could accept or reject this friendship request using the "Friendship Requests" menu of your mobile application or at address www.traceper.com.');
					
				//echo $message;
				
				if($this->SMTP_UTF8_mail(Yii::app()->params->noreplyEmail, 'Traceper', $friendCandidateEmail, $friendCandidateName, $requesterName.', '.Yii::t('users', 'wants to be your friend at Traceper'), $message))
				{
					//Mail gönderildi
				}
				else
				{
					//Mail gönderilirken hata oluştu
				}
				
				//Language recovery should be done after sending the mail, because some generic message is added also in SMTP_UTF8_mail()
				if($isTranslationRequired == true) //Recover the language if needed for mobile
				{
					if($mobileLang == 'tr')
					{
						Yii::app()->language = 'en';
					}
					else
					{
						Yii::app()->language = 'tr';
					}
				}				
			}						
		}
		else
		{
			$result = "-3"; //Missing parameter
		}
		
		echo CJSON::encode(array(
				"result"=>$result,
		));
		
		Yii::app()->end();
	}
	
	public function actionAddAsFriendIfAlreadyMember()
	{
		$result = 0;
		
		if (isset($_REQUEST['facebookIdOfFriendCandidate']))
		{
			$facebookIdOfFriendCandidate = (int)$_REQUEST['facebookIdOfFriendCandidate'];
			
			if (isset($_REQUEST['traceperIdOfFriendshipRequestingMember']))
			{
				$traceperIdOfFriendshipRequestingMember = (int)$_REQUEST['traceperIdOfFriendshipRequestingMember'];
				
				$friendCandidateRecord = Users::model()->find(array('condition'=>'fb_id=:fb_id', 'params'=>array(':fb_id'=>$facebookIdOfFriendCandidate)));

			    if($friendCandidateRecord != null) //The friend candidate with the given Facebook ID is also a Traceper member 
			    {
			    	$done = Friends::model()->addAsFriend($traceperIdOfFriendshipRequestingMember, $friendCandidateRecord->Id);
			    	
			    	if ($done == true) {
			    		$result = 1; //If the frienship is made successfully, return 1
			    	}
			    	else  if ($done == null) {
			    		$result = -1; //If there occurs a problem during friendship process, return -1
			    	}
			    	else
			    	{
			    		$result = -1; //If there occurs a problem during friendship process, return -1
			    	}					
			    }
			    else //The friend candidate with the given Facebook ID is not a Traceper member, so return 0
			    {
			    	$result = 0;
			    }				
			}			
		}
		
		echo CJSON::encode(array(
				"result"=>$result,
		));
		Yii::app()->end();
	}	
	
	private function prepareJson($dataProvider){

		$rows = $dataProvider->getData();
		$itemCount = count($rows);

		$str = '';
		for ($i = 0; $i < $itemCount; $i++) {
			if ($i > 0)  {
				$str .= ",";
			}
			$str .= $this->getUserJsonItem($rows[$i]);
			
		}
		$pagination = $dataProvider->getPagination();
		$currentPage = $pagination->currentPage + 1;
		$str = '{"userlist": ['.$str.'], "pageNo":"'.$currentPage .'", "pageCount":"'.$pagination->pageCount.'"}';

		return $str;
	}
	
	private function prepareSearchUserResultJson($dataProvider) {
		$row = $dataProvider->getData();
		$itemCount = count($row);
		$str = '';
		for ($i = 0; $i < $itemCount; $i++) {
			$row[$i]['id'] = isset($row[$i]['id']) ? $row[$i]['id'] : null;
			$row[$i]['Name'] = isset($row[$i]['Name']) ? $row[$i]['Name'] : null;
			$row[$i]['fb_id']= isset($row[$i]['fb_id']) ? $row[$i]['fb_id'] : null;
			$row[$i]['account_type'] = isset($row[$i]['account_type']) ? $row[$i]['account_type'] : null;
			$row[$i]['status'] = isset($row[$i]['status']) ? $row[$i]['status'] : null;
			$row[$i]['requester'] = isset($row[$i]['requester']) ? $row[$i]['requester'] : null;
			
			$str .= CJSON::encode(array(
					'id'=>$row[$i]['id'],
					'Name'=>$row[$i]['Name'],
					'fb_id'=>$row[$i]['fb_id'],
					'account_type'=>$row[$i]['account_type'],
					'status'=>$row[$i]['status'],
					'requester'=>$row[$i]['requester'],
			)).',';
		}
		
		$result = null;
		
		$pagination = $dataProvider->getPagination();
		$currentPage = $pagination->currentPage + 1;
				
		if($pagination->pageCount > 0) //If there exists any result
		{
			$result = "1";
			$str = '{"result":"'.$result.'", "userlist": ['.$str.'], "pageNo":"'.$currentPage .'", "pageCount":"'.$pagination->pageCount.'"}';
		}
		else
		{
			$result = "0";
			$str = '{"result":"'.$result.'"}';
		}

		return $str;
	}
	
	private function preparePastPointsJson($dataProvider) {
		$rows = $dataProvider->getData();
		$itemCount = count($rows);
		
		$str = '';
		for ($i = 0; $i < $itemCount; $i++) {
			
			$rows[$i]['latitude'] = isset($rows[$i]['latitude']) ? $rows[$i]['latitude'] : null;
			$rows[$i]['longitude'] = isset($rows[$i]['longitude']) ? $rows[$i]['longitude'] : null;
			$rows[$i]['altitude'] = isset($rows[$i]['altitude']) ? $rows[$i]['altitude'] : null;
			$rows[$i]['dataArrivedTime'] = isset($rows[$i]['dataArrivedTime']) ? $rows[$i]['dataArrivedTime'] : null;
			$rows[$i]['deviceId'] = isset($rows[$i]['deviceId']) ? $rows[$i]['deviceId'] : null;
			$rows[$i]['dataCalculatedTime'] = isset($rows[$i]['dataCalculatedTime']) ? $rows[$i]['dataCalculatedTime'] : null;
			
			if ($i > 0) {
				$str .= ',';
			}

			if(Yii::app()->language == 'tr')
			{
				$timestamp = strtotime($rows[$i]['dataArrivedTime']);
				$rows[$i]['dataArrivedTime'] = strftime("%d ", $timestamp).Yii::t('common', strftime("%b", $timestamp)).strftime(" %Y %H:%M:%S", $timestamp);
			
				$timestamp = strtotime($rows[$i]['dataCalculatedTime']);
				$rows[$i]['dataCalculatedTime'] = strftime("%d ", $timestamp).Yii::t('common', strftime("%b", $timestamp)).strftime(" %Y %H:%M:%S", $timestamp);
			}			
			
			$str .= CJSON::encode(array(
						'latitude'=>$rows[$i]['latitude'],
						'longitude'=>$rows[$i]['longitude'],
						'altitude'=>$rows[$i]['altitude'],
						'calculatedTime'=>$rows[$i]['dataCalculatedTime'],
						'time'=>$rows[$i]['dataArrivedTime'],
						'deviceId'=>$rows[$i]['deviceId'],
				));
		}
		
		$pagination = $dataProvider->getPagination();
		$currentPage = $pagination->currentPage + 1;
		$str='{"userwashere":['.$str.'], "pageNo":"'.$currentPage .'", "pageCount":"'.$pagination->pageCount.'"}';
		return $str;
		
	}

	private function getUserJsonItem($row) {
		$row['id'] = isset($row['id']) ? $row['id'] : "";
		//		$row->username = isset($row->username) ? $row->username : null;
		$row['isFriend'] = isset($row['isFriend']) ? $row['isFriend'] : 0;
		$row['realname'] = isset($row['Name']) ? $row['Name'] : "";
		$row['latitude'] = isset($row['latitude']) ? $row['latitude'] : "";
		$row['longitude'] = isset($row['longitude']) ? $row['longitude'] : "";
		$row['altitude'] = isset($row['altitude']) ? $row['altitude'] : "";
		$row['lastLocationAddress'] = isset($row['lastLocationAddress']) ? $row['lastLocationAddress'] : "";
		$row['dataArrivedTime'] = isset($row['dataArrivedTime']) ? $row['dataArrivedTime'] : "";
		$row['message'] = isset($row['message']) ? $row['message'] : "";
		$row['deviceId'] = isset($row['deviceId']) ? $row['deviceId'] : "";
		$row['userType'] = isset($row['userType']) ? $row['userType'] : "";		
		$row['status_message'] = isset($row['status_message']) ? $row['status_message'] : "";
		$row['dataCalculatedTime'] = isset($row['dataCalculatedTime']) ? $row['dataCalculatedTime'] : "";
		$row['gp_image'] = "";
		$row['fb_id'] = isset($row['fb_id']) ? $row['fb_id'] : "";
		$row['g_id'] = "";
		$row['account_type'] =  isset($row['account_type']) ? $row['account_type'] : "";
		
		if(Yii::app()->language == 'tr')
		{
			$timestamp = strtotime($row['dataArrivedTime']);
			$row['dataArrivedTime'] = strftime("%d ", $timestamp).Yii::t('common', strftime("%b", $timestamp)).strftime(" %Y %H:%M:%S", $timestamp);

			$timestamp = strtotime($row['dataCalculatedTime']);
			$row['dataCalculatedTime'] = strftime("%d ", $timestamp).Yii::t('common', strftime("%b", $timestamp)).strftime(" %Y %H:%M:%S", $timestamp);
		}
				
		$bsk=   CJSON::encode( array(
				'user'=>$row['id'],
				'isFriend'=>$row['isFriend'],
				'realname'=>$row['realname'],
				'latitude'=>$row['latitude'],
				'longitude'=>$row['longitude'],
				'altitude'=>$row['altitude'],
				'address'=>$row['lastLocationAddress'],
				'calculatedTime'=>$row['dataCalculatedTime'],
				'time'=>$row['dataArrivedTime'],
				'message'=>$row['message'],
				'status_message'=>$row['status_message'],
				'deviceId'=>$row['deviceId'],
				'userType'=>$row['userType'],
				'gp_image'=>$row['gp_image'],
				'fb_id'=>$row['fb_id'],
				'g_id'=>$row['g_id'],
				'account_type'=>$row['account_type'],
		));
		
		if($row['id'] == 2)
		{
			//setlocale(LC_TIME, 'Turkish');
			//Fb::warn(strftime("%d %b %Y %H:%M:%S", strtotime($row['dataArrivedTime'])), "getUserJsonItem() - dataCalculatedTime");
		}

		return $bsk;
	}	
}