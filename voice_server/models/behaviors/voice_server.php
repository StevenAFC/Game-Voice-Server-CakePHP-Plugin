<?php
App::import('Sanitize');

class VoiceServerBehavior extends ModelBehavior {
	
    function setup(&$model, $settings = array()) {		
        $default = array('cache_time' => 0);
        
        if (!isset($this->settings[$model->name])) {
            $this->settings[$model->name] = $default;
        }
        
		$this->settings[$model->name] = array_merge($this->settings[$model->name], ife(is_array($settings), $settings, array()));
    } 
	
	function afterFind ($model, $results, $primary) {
		foreach($results as $index => $result) {
			if(!empty($result[$model->name]['id'])) {
				/*$using_cache = false;
				if(!empty($result[$model->name]['cache'])) {
					if(time() < (strtotime($result[$model->name]['cache_time']) + $this->settings[$model->name]['cache_time'])) {
						$results[$index][$model->name]['Data'] = unserialize(base64_decode($result[$model->name]['cache']));
						$using_cache = true;
					}
				}
				
				if(!$using_cache) {*/
					$live_data = $this->{$result[$model->name]['protocol']}($model, $result);
					
					$this->saveCache($model, $result, $live_data);

					$results[$index][$model->name]['Data'] = $live_data;
				/*}*/
			}
		}

		return $results;
	}
	
	function beforeSave($model) {
		$model->data[$model->name]['cache'] = "";
	}
	
	function ventrilo($model, $server) {
		try {
			App::import('Vendor', 'Voice.Ventrilo', array('file' => 'ventrilo'.DS.'ventrilostatus.php'));

			$stat = new CVentriloStatus;
			$path = App::pluginPath('voice_server') . 'webroot' . DS . 'files' . DS . 'ventrilo_status';
			
			//Windows only:
			$path = str_replace('\\', '/', $path);
			//**************

			$stat->m_cmdprog 	= $path;
			$stat->m_cmdcode	= 2;
			$stat->m_cmdhost	= $server[$model->name]['ip'];
			$stat->m_cmdport	= $server[$model->name]['port'];
			$stat->m_cmdpass	= null;
			
			$rc = $stat->Request();

			if(!$rc == 0) {
				throw new Exception();
			}
			
			/****Data Arranging****/
			$userList = array();

			foreach($stat->m_clientlist as $client) {
				array_push($userList, array('id' => $client->m_name, 'nickname' => Sanitize::escape($client->m_name), 'pid' => $client->m_cid, 'type' => 'user'));
			}

			$channelList = array();

			foreach($stat->m_channellist as $channel) {
				array_push($channelList, array('id' => $channel->m_cid, 'pid' => $channel->m_pid, 'channel_name' => Sanitize::escape($channel->m_name), 'type' => 'channel'));
			}
			
			$consolidated = array();
			$consolidated = array_merge($userList, $channelList);
			
			$consolidated = $this->convertToTree($consolidated);
			
			$consolidated['name'] = Sanitize::escape($stat->m_name);
			$consolidated['client_count'] = $stat->m_clientcount;
			$consolidated['client_max'] = $stat->m_maxclients;
			$consolidated['status'] = 1;
			$consolidated['protocol'] = "Ventrilo";

			return $consolidated;
			
		} catch(Exception $error) {
		
			$error = array('error' => 'Unabled to connect');
			$error['protocol'] = 'Ventrilo';
			$error['status'] = 0;
			
			return $error;
		}
	}

	function teamspeak($model,$server) {
		try {
			App::import('Vendor', 'Voice.TeamSpeak', array('file' => 'teamspeak'.DS.'teamspeakdisplay.php'));
			
			if(empty($server[$model->name]['query_port'])) {
				$server[$model->name]['query_port'] = 51234;
			}
			
			$teamspeakDisplay = new teamspeakDisplayClass;
			
			$settings = $teamspeakDisplay->getDefaultSettings();

			$settings["serveraddress"] = $server[$model->name]['ip'];
			$settings["serverudpport"] = $server[$model->name]['port'];
			$settings["serverqueryport"] = $server[$model->name]['query_port'];

			$server_data = $teamspeakDisplay->displayTeamspeakEx($settings);

			if(!$server_data['queryerror'] == 0) {
				throw new Exception();
			}
			
			/****Data Arranging****/
			$userList = array();

			foreach($server_data['playerlist'] as $client) {
				array_push($userList, array('id' => $client['playerid'], 'nickname' => Sanitize::escape($client['playername']), 'pid' => $client['channelid'], 'type' => 'user'));
			}

			$channelList = array();

			foreach($server_data['channellist'] as $channel) {
				array_push($channelList, array('id' => $channel['channelid'], 'pid' => $channel['parent'], 'channel_name' => Sanitize::escape($channel['channelname']), 'type' => 'channel'));
			}
			
			$consolidated = array();
			$consolidated = array_merge($userList, $channelList);
			
			$consolidated = $this->convertToTree($consolidated);
			
			$consolidated['name'] = Sanitize::escape($server_data['serverinfo']['server_name']);
			$consolidated['client_count'] = $server_data['serverinfo']['server_currentusers'];
			$consolidated['client_max'] = $server_data['serverinfo']['server_maxusers'];
			$consolidated['status'] = ($server_data['queryerror'] == 0 ? 1 : 0);
			$consolidated['protocol'] = "Teamspeak";

			return $consolidated;
			
		} catch(Exception $error) {
			$error = array('error' => 'Unabled to connect');
			$error['protocol'] = "Teamspeak 3";
			$error['status'] = 0;
			return $error;
		}
	}
	
	
	//teamspeak 3 server query function
	function teamspeak3($model, $server) {
		try {
			App::import('Vendor', 'Voice.TeamSpeak3', array('file' => 'teamspeak3'.DS.'TeamSpeak3.php'));
		
			if(empty($server[$model->name]['query_port'])) {
				$server[$model->name]['query_port'] = 10011;
			}
		
			$server_data = TeamSpeak3::factory("serverquery://".$server[$model->name]['ip'].":".$server[$model->name]['query_port']."/?server_port=".$server[$model->name]['port']."#no_query_clients");
			
			/****Data Arranging****/
			$userList = array();
	
			foreach($server_data->clientList() as $client) {
				array_push($userList, array('id' => $client['clid'], 'nickname' => Sanitize::escape($client['client_nickname']->toString()), 'pid' => $client['cid'], 'type' => 'user'));
			}
			
			$channelList = array();
			
			foreach($server_data->channelList() as $channel) {
				array_push($channelList, array('id' => $channel['cid'], 'pid' => $channel['pid'], 'channel_name' => Sanitize::escape($channel['channel_name']), 'type' => 'channel'));
			}
			
			$consolidated = array_merge($userList, $channelList);
			
			$consolidated = $this->convertToTree($consolidated);
			
			$consolidated['name'] = Sanitize::escape($server_data['virtualserver_name']->toString());
			$consolidated['client_count'] = $server_data['virtualserver_clientsonline'];
			$consolidated['client_max'] = $server_data['virtualserver_maxclients'];
			$consolidated['status'] = ($server_data['virtualserver_status'] == 'online' ? 1 : 0);
			$consolidated['protocol'] = "Teamspeak 3";
			
			return $consolidated;

		} catch(Exception $error) {
			if($error->getCode() == 3329) {
				$error = array('error' => 'Temporarily banned from server retry later');
			} else {
				$error = array('error' => 'Unabled to connect ('.$error->getCode().')');
			}
			$error['protocol'] = "Teamspeak 3";
			return $error;
		}
	}
	
	function protocols() {
		$protocols = array(
			'teamspeak' => 'TeamSpeak',
			'teamspeak3' => 'TeamSpeak 3',
			'ventrilo' => 'Ventrilo'
			);
	
		return $protocols;
	}
	
	private function saveCache($model, $result, $live_data) {
		$cache = base64_encode(serialize($live_data));

		$query = 	"UPDATE " . $model->useTable . "
					SET `cache`='".$cache."', `cache_time`='".date("Y-m-d H:i:s")."' 
					WHERE ".$model->useTable.".id = ". $result[$model->name]['id'];
		return $model->query($query);
	}
	
	private function convertToTree(array $list, $idField = 'id', $parentIdField = 'pid', $childNodesField = 'childNodes') {
		$lookup = array();
		
		foreach($list as $item)	{
			$item['children'] = array();
			$lookup[$item[$idField]] = $item;
		}
		
		$tree = array();
		
		foreach($lookup as $id => $foo) {
			$item = &$lookup[$id];
			
			if($item[$parentIdField] <= 0) {
				$tree[$id] = &$item;
			} else if( isset( $lookup[$item[$parentIdField]] ) ) {
				$lookup[$item[$parentIdField]]['children'][$id] = &$item;
			} else {
				$tree['_orphans_'][$id] = &$item;
			}
		}
		
		return $tree;
	}
}

?>