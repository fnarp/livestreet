<?php
/*-------------------------------------------------------
*
*   LiveStreet Engine Social Networking
*   Copyright © 2008 Mzhelskiy Maxim
*
*--------------------------------------------------------
*
*   Official site: www.livestreet.ru
*   Contact e-mail: rus.engine@gmail.com
*
*   GNU General Public License, version 2:
*   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
---------------------------------------------------------
*/

/**
 * ACL(Access Control List)
 * Модуль для разруливания ограничений по карме/рейтингу юзера
 *
 */
class LsACL extends Module {
	/**
	 * Коды ответов на запрос о возможности 
	 * пользователя голосовать за блог
	 */
	const CAN_VOTE_BLOG_FALSE = 0;	
	const CAN_VOTE_BLOG_TRUE = 1;
	const CAN_VOTE_BLOG_ERROR_CLOSE = 2;
	
	/**
	 * Инициализация модуля
	 *
	 */
	public function Init() {		
		
	}
		
	/**
	 * Проверяет может ли пользователь создавать блоги
	 *
	 * @param Entity_User $oUser
	 * @return bool
	 */
	public function CanCreateBlog(UserEntity_User $oUser) {
		if ($oUser->getRating()>=Config::Get('acl.create.blog.rating')) {
			return true;
		}
		return false;
	}
	
	/**
	 * Проверяет может ли пользователь создавать топики в определенном блоге
	 *
	 * @param Entity_User $oUser
	 * @param Entity_Blog $oBlog
	 * @return bool
	 */
	public function CanAddTopic(UserEntity_User $oUser, BlogEntity_Blog $oBlog) {
		/**
		 * Если юзер является создателем блога то разрешаем ему постить
		 */
		if ($oUser->getId()==$oBlog->getOwnerId()) {
			return true;
		}
		/**
		 * Если рейтинг юзера больше либо равен порогу постинга в блоге то разрешаем постинг
		 */
		if ($oUser->getRating()>=$oBlog->getLimitRatingTopic()) {
			return true;
		}
		return false;
	}

	/**
	 * Проверяет может ли пользователь создавать комментарии
	 *
	 * @param  Entity_User $oUser
	 * @return bool
	 */
	public function CanPostComment(UserEntity_User $oUser) {
		if ($oUser->getRating()>=Config::Get('acl.create.comment.rating')) {
			return true;
		}
		return false;
	}
	
	/**
	 * Проверяет может ли пользователь создавать комментарии по времени(например ограничение максимум 1 коммент в 5 минут)
	 *
	 * @param Entity_User $oUser
	 * @return bool
	 */
	public function CanPostCommentTime(UserEntity_User $oUser) {
		if (Config::Get('acl.create.comment.limit_time')>0 and $oUser->getDateCommentLast()) {
			$sDateCommentLast=strtotime($oUser->getDateCommentLast());
			if ($oUser->getRating()<Config::Get('acl.create.comment.limit_time_rating') and ((time()-$sDateCommentLast)<Config::Get('acl.create.comment.limit_time'))) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Проверяет может ли пользователь создавать топик по времени
	 *
	 * @param  Entity_User $oUser
	 * @return bool
	 */
	public function CanPostTopicTime(UserEntity_User $oUser) {
		// Для администраторов ограничение по времени не действует
		if($oUser->isAdministrator() 
			or Config::Get('acl.create.topic.limit_time')==0 
				or $oUser->getRating()>=Config::Get('acl.create.topic.limit_time_rating')) 
					return true;
		
		/**
		 * Проверяем, если топик опубликованный меньше чем acl.create.topic.limit_time секунд назад
		 */
		$aTopics=$this->Topic_GetLastTopicsByUserId($oUser->getId(),Config::Get('acl.create.topic.limit_time'));
		
		if(isset($aTopics['count']) and $aTopics['count']>0){ 
			return false;
		}
		return true;
	}

	/**
	 * Проверяет может ли пользователь отправить инбокс по времени
	 *
	 * @param  Entity_User $oUser
	 * @return bool
	 */
	public function CanSendTalkTime(UserEntity_User $oUser) {
		// Для администраторов ограничение по времени не действует
		if($oUser->isAdministrator() 
			or Config::Get('acl.create.talk.limit_time')==0 
				or $oUser->getRating()>=Config::Get('acl.create.talk.limit_time_rating')) 
					return true;
		
		/**
		 * Проверяем, если топик опубликованный меньше чем acl.create.topic.limit_time секунд назад
		 */
		$aTalks=$this->Talk_GetLastTalksByUserId($oUser->getId(),Config::Get('acl.create.talk.limit_time'));
		
		if(isset($aTalks['count']) and $aTalks['count']>0){ 
			return false;
		}
		return true;
	}	

	/**
	 * Проверяет может ли пользователь создавать комментарии к инбоксу по времени
	 *
	 * @param  Entity_User $oUser
	 * @return bool
	 */
	public function CanPostTalkCommentTime(UserEntity_User $oUser) {
		// Для администраторов ограничение по времени не действует
		if($oUser->isAdministrator() 
			or Config::Get('acl.create.talk_comment.limit_time')==0 
				or $oUser->getRating()>=Config::Get('acl.create.talk_comment.limit_time_rating')) 
					return true;
		/**
		 * Проверяем, если топик опубликованный меньше чем acl.create.topic.limit_time секунд назад
		 */
		$aTalkComments=$this->Comment_GetCommentsByUserId($oUser->getId(),'talk',1,1);
		/**
		 * Если комментариев не было
		 */
		if(!is_array($aTalkComments) or $aTalkComments['count']==0){ 
			return true;
		}

		$oComment = array_shift($aTalkComments['collection']);
		$sDate = strtotime($oComment->getDate());
		
		if($sDate and ((time()-$sDate)<Config::Get('acl.create.talk_comment.limit_time'))) {
			return false;
		}
		return true;
	}
	
	/**
	 * Проверяет может ли пользователь создавать комментарии используя HTML
	 *
	 * @param Entity_User $oUser
	 * @return bool
	 */
	public function CanUseHtmlInComment(UserEntity_User $oUser) {
		return true;
	}
	
	/**
	 * Проверяет может ли пользователь голосовать за конкретный комментарий
	 *
	 * @param Entity_User $oUser
	 * @param Entity_TopicComment $oComment
	 * @return bool
	 */
	public function CanVoteComment(UserEntity_User $oUser, CommentEntity_Comment $oComment) {
		if ($oUser->getRating()>=Config::Get('acl.vote.comment.rating')) {
			return true;
		}
		return false;
	}
	
	/**
	 * Проверяет может ли пользователь голосовать за конкретный блог
	 *
	 * @param Entity_User $oUser
	 * @param Entity_Blog $oBlog
	 * @return bool
	 */
	public function CanVoteBlog(UserEntity_User $oUser, BlogEntity_Blog $oBlog) {
		/**
		 * Если блог закрытый, проверяем является ли пользователь его читателем
		 */
		if($oBlog->getType()=='close') {
			$oBlogUser = $this->Blog_GetBlogUserByBlogIdAndUserId($oBlog->getId(),$oUser->getId());
			if(!$oBlogUser || $oBlogUser->getUserRole()<LsBlog::BLOG_USER_ROLE_GUEST) {
				return self::CAN_VOTE_BLOG_ERROR_CLOSE;
			}
		}
		
		if ($oUser->getRating()>=Config::Get('acl.vote.blog.rating')) {
			return self::CAN_VOTE_BLOG_TRUE;
		}
		return self::CAN_VOTE_BLOG_FALSE;
	}
	
	/**
	 * Проверяет может ли пользователь голосовать за конкретный топик
	 *
	 * @param Entity_User $oUser
	 * @param Entity_Topic $oTopic
	 * @return bool
	 */
	public function CanVoteTopic(UserEntity_User $oUser, TopicEntity_Topic $oTopic) {
		if ($oUser->getRating()>=Config::Get('acl.vote.topic.rating')) {
			return true;
		}
		return false;
	}
	
	/**
	 * Проверяет может ли пользователь голосовать за конкретного пользователя
	 *
	 * @param Entity_User $oUser
	 * @param Entity_User $oUserTarget
	 * @return bool
	 */
	public function CanVoteUser(UserEntity_User $oUser, UserEntity_User $oUserTarget) {
		if ($oUser->getRating()>=Config::Get('acl.vote.user.rating')) {
			return true;
		}
		return false;
	}
	/**
	 * Проверяет можно ли юзеру слать инвайты
	 *
	 * @param UserEntity_User $oUser
	 * @return unknown
	 */
	public function CanSendInvite(UserEntity_User $oUser) {
		if ($this->User_GetCountInviteAvailable($oUser)==0) {
			return false;
		}
		return true;
	}
}
?>