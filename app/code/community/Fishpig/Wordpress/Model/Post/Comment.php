<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Post_Comment extends Mage_Core_Model_Abstract
{
	/**
	 * Base URL used for Gravatar images
	 *
	 * @var const string
	 */
	const GRAVATAR_BASE_URL = 'http://www.gravatar.com/avatar/';
	
	public function _construct()
	{
		$this->_init('wordpress/post_comment');
	}
	
	/**
	  * Returns a collection of comments for a certain post
	  *
	  * @param int $postId
	  * @param bool $isApproved
	  * @return Fishpig_Wordpress_Model_Mysql4_Post_Comment_Collection
	  */
	public function loadByPostId($postId, $isApproved = true)
	{
		$comments = Mage::getResourceModel('wordpress/post_comment_collection')
			->addPostIdFilter($postId);
								
		if ($isApproved) {
			$comments->addCommentApprovedFilter();
		}
		
		return $comments;
	}
	
	/**
	 * Set the post this comment is associated to
	 *
	 * @param Fishpig_Wordpress_Model_Post $post
	 * @return Fishpig_Wordpress_Model_Post_Comment
	 */
	public function setPost(Fishpig_Wordpress_Model_Post $post)
	{
		$this->setPostId($post->getId());
		$this->setData('comment_post_ID', $post->getId());

		return parent::setData('post', $post);
	}

	/**
	 * Retrieve the post that this comment is associated to
	 *
	 * @return Fishpig_Wordpress_Model_Post
	 */
	public function getPost()
	{
		if (!$this->hasPost()) {
			$post = Mage::getModel('wordpress/post')->load($this->getData('comment_post_ID'));
				
			if ($post->getId()) {
				if ($post->getPostType() == 'page') {
					$post = Mage::getModel('wordpress/page')->load($post->getId());
				}

				$this->setPost($post);
			}
			else {
				$this->setPost(false);
			}
		}
		
		return $this->getData('post');
	}

	/**
	 * Returns the comment date
	 * If no format is specified, the default format is used from the Magento config
	 *
	 * @return string
	 */
	public function getCommentDate($format = null)
	{
		return Mage::helper('wordpress')->formatDate($this->getData('comment_date'), $format);
	}
	
	/**
	 * Returns the comment time
	 * If no format is specified, the default format is used from the Magento config
	 *
	 * @return string
	 */
	public function getCommentTime($format = null)
	{
		return Mage::helper('wordpress')->formatTime($this->getData('comment_date'), $format);
	}
	
	/**
	 * Return the URL for the comment author
	 *
	 * @return string
	 */
	public function getCommentAuthorUrl()
	{
		if ($url = $this->_getData('comment_author_url')) {
			if (strpos($url, 'http') !== 0) {
				$url = 'http://' . $url;
			}
			
			return $url;
		}
		
		return '#';
	}
	
	/**
	 * Send a notification email to the blog Admin
	 *
	 * @return bool
	 */
	public function sendNotificationEmail()
	{
		// Coming soon
	}

	/**
	 * Send a moderation email to the blog Admin
	 *
	 * @return bool
	 */	
	public function sendModerationEmail()
	{
		if ($emailHtml = $this->_getModerationHtmlContent()) {
			$helper = Mage::helper('wordpress');

			$mail = new Zend_Mail();
			$mail->setBodyHtml($emailHtml);
			$mail->setFrom($this->_getNotificiationFromEmail(), $helper->__('WordPress'));
			$mail->addTo($helper->getWpOption('admin_email'));
			$mail->setSubject(sprintf('[%s] Please moderate: "%s"', $helper->getWpOption('blogname'), $this->getPost()->getPostTitle()));
			
			try {
				$mail->send();		
				return true;
			}
			catch (Exception $e) {
				$helper->log($e->getMessage());
				return false;
			}
		}
	}

	/**
	 * Retrieve the WordPress domain by getting the URL and the path
	 *
	 * @return string
	 */
	protected function _getNotificiationFromEmail()
	{	
		$url = parse_url(Mage::getStoreConfig('web/unsecure/base_url'));
		$host = $url['host'];
		
		if (strpos($host, 'www.') !== false) {
			$host = substr($host, 4);
		}
	
		return 'wordpress@' . $host;
	}
	
	protected function _afterSave()
	{
		Mage::helper('wordpress/plugin_commentReplyNotification')->notify($this);
		
//		Mage::getResourceModel('wordpress/post')->updatePostCommentCount($this->_getData('comment_post_ID'));
		
		return parent::_afterSave();
	}
	
	/**
	 * Retrieve the HTML for the moderation email
	 *
	 * @return string
	 */
	protected function _getModerationHtmlContent()
	{
		$moderatedCommentCount = count($this->getCollection()->addFieldToFilter('comment_approved', array('neq' => 1)));

		return Mage::getSingleton('core/layout')
			->createBlock('core/template')
			->setTemplate('wordpress/email/comment/moderation.phtml')
			->setComment($this)
			->setModeratedCommentCount($moderatedCommentCount)
			->toHtml();
	}
	
	public function getGuid()
	{
		return Mage::helper('wordpress')->getUrl('?p='. $this->getPost()->getId() . '#comment-' . $this->getId());
	}
	
	/**
	 * Retrieve the URL for this comment
	 *
	 * @return string
	 */
	public function getUrl()
	{
		if (!$this->hasUrl()) {
			if ($post = $this->getPost()) {
				$pageId = $this->getCommentPageId();
				$fragment = '#comment-' . $this->getId();
				
				if (Mage::helper('wordpress/post')->permalinkHasTrainingSlash()) {
					$fragment = '/' . $fragment;
				}

				$this->setUrl(rtrim($post->getUrl(), '/') . '/comment-page-' . $pageId . $fragment);
			}
		}
		
		return $this->getData('url');
	}
	
	/**
	 * Retrieve the page number that the comment is on
	 *
	 * @return int
	 */
	public function getCommentPageId()
	{
		if (!$this->hasCommentPageId()) {
			$this->setCommentPageId(1);
			if ($post = $this->getPost()) {
				$totalComments = count($post->getComments());
				$commentsPerPage = Mage::helper('wordpress/post')->getCommentsPerPage();

				if ($commentsPerPage > 0 && $totalComments > $commentsPerPage) {
					$it = 0;
					
					foreach($post->getComments() as $comment) { ++$it; 
						if ($this->getId() == $comment->getId()) {
							$position = $it;
							break;
						}
					}
				
					$this->setCommentPageId(ceil($position / $commentsPerPage));
				}
				else {
					$this->setCommentPageId(1);
				}
			}
		}
		
		return $this->getData('comment_page_id');
	}
	
	/**
	 * Retrieve the Gravatar URL for the comment
	 *
	 * @return null|string
	 */
	public function getGravatarUrl()
	{
		if (!$this->hasGravatarUrl()) {
			if (Mage::helper('wordpress')->getWpOption('show_avatars')) {
				if ($this->getCommentAuthorEmail()) {
					$url = self::GRAVATAR_BASE_URL
						. md5(strtolower($this->getCommentAuthorEmail()))
						. '?=&amp;r=' . strtolower(Mage::helper('wordpress')->getWpOption('avatar_rating'))
						. '&amp;s=50'
						. '&amp;d=' . $this->_getDefaultGravatarCode();

						
					$this->setGravatarUrl($url);
				}
			}
		}
		
		return $this->_getData('gravatar_url');
	}
	
	/**
	 * Convert the Gravatar code to the default code
	 *
	 * @return string
	 */
	protected function _getDefaultGravatarCode()
	{
		$code = Mage::helper('wordpress')->getWpOption('avatar_default');
		$defaults = array(
			'mystery' => 'mm',
			'blank' => 'mm',
			'gravatar_default' => '',
		);
		
		if (isset($defaults[$code])) {
			return $defaults[$code];
		}
		
		return $code;
	}
}
