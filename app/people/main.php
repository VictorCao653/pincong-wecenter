<?php
/*
+--------------------------------------------------------------------------
|   WeCenter [#RELEASE_VERSION#]
|   ========================================
|   by WeCenter Software
|   © 2011 - 2014 WeCenter. All Rights Reserved
|   http://www.wecenter.com
|   ========================================
|   Support: WeCenter@qq.com
|
+---------------------------------------------------------------------------
*/


if (!defined('IN_ANWSION'))
{
	die;
}

class main extends AWS_CONTROLLER
{
	public function get_access_rule()
	{
		$rule_action['rule_type'] = 'white';

		if ($this->user_info['permission']['visit_people'] AND $this->user_info['permission']['visit_site'])
		{
			$rule_action['actions'] = array(
				'index',
				'square'
			);
		}

		return $rule_action;
	}

	public function index_action()
	{
		if (is_digits($_GET['id']))
		{
			if (!$user = $this->model('account')->get_user_info_by_uid($_GET['id']))
			{
				$user = $this->model('account')->get_user_info_by_username($_GET['id']);
			}
		}
		else
		{
			$user = $this->model('account')->get_user_info_by_username($_GET['id']);
		}

		if (!$user)
		{
			HTTP::error_404();
		}

		if (urldecode($user['url_token']) != $_GET['id'])
		{
			HTTP::redirect('/people/' . $user['url_token']);
		}

		$user['reputation_group_name'] = $this->model('usergroup')->get_user_group_name_by_user_info($user);

		$user['data'] = unserialize_array($user['extra_data']);

		$this->model('people')->update_view_count($user['uid'], session_id());

		TPL::assign('user', $user);

		TPL::assign('user_follow_check', $this->model('follow')->user_follow_check($this->user_id, $user['uid']));

		$this->crumb(AWS_APP::lang()->_t('%s 的个人主页', $user['user_name']));

		TPL::import_css('css/user.css');

		TPL::assign('fans_list', $this->model('follow')->get_user_fans($user['uid'], 5));
		TPL::assign('friends_list', $this->model('follow')->get_user_friends($user['uid'], 5));

		TPL::output('people/index');
	}

	public function index_square_action()
	{

		if (!$_GET['page'])
		{
			$_GET['page'] = 1;
		}

		$this->crumb(AWS_APP::lang()->_t('用户列表'));

		$all_groups = $this->model('usergroup')->get_all_groups();
		foreach ($all_groups as $key => $val)
		{
			if ($val['type'] == 2)
			{
				$custom_group[] = $val;
			}
		}

		$order = 'reputation DESC, uid ASC';

		$group_id = intval($_GET['group_id']);
		if ($group_id > 0 AND $all_groups[$group_id]['type'] == 2)
		{
			$where[] = 'group_id = ' . $group_id;
			$url_param[] = 'group_id-' . $group_id;
		}

		$is_forbidden = intval($_GET['forbidden']);
		$is_flagged = intval($_GET['flagged']);
		if ($is_forbidden OR $is_flagged)
		{
			$order = 'user_update_time ASC, uid ASC';

			if ($is_forbidden)
			{
				$where[] = 'forbidden <> 0';
				$url_param[] = 'forbidden-1';
			}

			if ($is_flagged)
			{
				$where[] = 'flagged <> 0';
				$url_param[] = 'flagged-1';
			}
		}
		else
		{
			$where[] = 'forbidden = 0';
			$where[] = 'flagged = 0';
		}

		if ($_GET['sort_key'] == 'uid')
		{
			$order = 'uid ASC';
			$url_param[] = 'sort_key-uid';
		}

		$where = implode(' AND ', $where);

		$users_list = $this->model('account')->get_user_list($where, calc_page_limit($_GET['page'], get_setting('contents_per_page')), $order);

		TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
			'base_url' => url_rewrite('/people/') . implode('__', $url_param),
			'total_rows' => $this->model('account')->get_user_count($where),
			'per_page' => get_setting('contents_per_page')
		))->create_links());

		if ($users_list)
		{
			if ($this->user_id)
			{
				foreach ($users_list as $key => $val)
				{
					$uids[] = $val['uid'];
				}

				if ($uids)
				{
					$users_follow_check = $this->model('follow')->users_follow_check($this->user_id, $uids);
					foreach ($users_list as $key => $val)
					{
						$users_list[$key]['focus'] = $users_follow_check[$val['uid']];
					}
				}
			}

			TPL::assign('users_list', array_values($users_list));
		}

		TPL::assign('custom_group', $custom_group);

		TPL::output('people/square');
	}
}
