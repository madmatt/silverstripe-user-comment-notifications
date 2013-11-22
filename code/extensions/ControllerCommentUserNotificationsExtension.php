<?php
/**
 * Class ControllerCommentUserNotificationsExtension
 * This class is injected into all {@link Controller} class instances, and provides some helper methods to determine
 * whether there are comment subscriptions for a given Member in the system
 */
class ControllerCommentUserNotificationsExtension extends Extension {
	/**
	 * Returns a {@link DataList} of {@link DataObject} objects that the currently logged in {@link Member} has
	 * requested. Note that this doesn't return {@link Comment} objects, as there may be many comments from one member,
	 * all of which may have `NotifyOfUpdates` selected, but in reality only one notification will be sent per comment
	 * thread.
	 *
	 * This can be used in any template as follows (assuming $Title exists all DataObjects that comments are bound to):
	 * <% loop CommentUserNotificationSubscriptions %>
	 *     <li>$Title.XML &ndash; <a href="$CommentUserNotificationUnsubscribeLink">unsubscribe</a></li>
	 * <% end_loop %>
	 */
	public function CommentUserNotificationSubscriptions() {
		return $this->CommentUserNotificationSubscriptionsFor(Member::currentUser());
	}

	/**
	 * This method is overly complex, because {@link Comment} doesn't have a standard 'Parent' has_one, as it can be
	 * attached to multiple different object types.
	 *
	 * @todo Can we fix this method without losing the flexibility that {@link Comment} provides?
	 *
	 * @see the above CommentUserNotificationSubscriptions() method for documentation
	 * @param Member $member The {@link Member} object to find comments posted by, where `NotifyOfUpdates` = 1
	 * @return ArrayList The list of {@link ArrayData} objects that can be shown in the template
	 */
	public function CommentUserNotificationSubscriptionsFor(Member $member) {
		if(!$member || !$member->isInDB()) return null; // No member (or no ID yet), so nothing to find

		$allComments = Comment::get()->filter(array(
			'AuthorID'        => $member->ID,
			'NotifyOfUpdates' => true
		));

		if(!$allComments) return null;

		$allObjects = new ArrayList();
		$allAddedComments = new ArrayList();

		// @todo O(n^2) :(
		foreach($allComments as $comment) {
			$alreadyAdded = false;

			foreach($allAddedComments as $obj) {
				if($comment->BaseClass == $obj->BaseClass && $comment->ParentID == $obj->ParentID) {
					$alreadyAdded = true;
					break;
				}
			}

			if(!$alreadyAdded) {
				$baseClass = $comment->BaseClass;
				$baseObject = $baseClass::get()->byID($comment->ParentID);

				if($baseObject) {
					// @todo This could return the actual DataObject that we're expecting (e.g. the SiteTree object),
					// but we can't add the 'CommentUserNotificationUnsubscribeLink' easily to it
					$allObjects->push(new ArrayData(array(
						'CommentUserNotificationUnsubscribeLink' => Controller::join_links(
								'CommentingController',
								'unsubscribenotification',
								$comment->ID
							),
						'Title' => $baseObject->Title
					)));

					$allAddedComments->push($comment); // Keep track of what we've already added
				}
			}
		}

		return $allObjects;
	}

	public function HasJustUnsubscribedFromUserCommentNotification() {
		$hasUnsubscribed = Session::get('CommentUserNotificationsUnsubscribed');
		Session::clear('CommentUserNotificationsUnsubscribed');

		return $hasUnsubscribed;
	}
}