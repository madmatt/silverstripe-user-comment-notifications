<?php
/**
 * Class CommentingControllerUserNotificationsExtension
 * Allows site members who are logged in to tick a box when posting their comment that will notify them when subsequent
 * comments are made on that object.
 *
 * @author Matt Peel <signups@madman.net.nz>
 */
class CommentUserNotificationsExtension extends DataExtension {
	/**
	 * Add a boolean to track which {@link Comment} objects (and therefore the {@link Member} that posted them) wantk
	 *
	 *
	 *
	 * to be notified when new comments are posted.
	 *
	 * @var array Additional database fields to add to the {@link Comment} class.
	 */
	private static $db = array(
		"NotifyOfUpdates" => "Boolean"
	);

	/**
	 * We hook into onAfterWrite() because we want to check this every time the comment is written - primarily because
	 * of the test that we perform to ensure that the comment isn't currently moderated. Most sites will moderate
	 * comments initially, and there's no point sending an email to a user if the comment is still awaiting moderation
	 * (and therefore the user can't see it yet).
	 *
	 * @todo This will lead to multiple emails being sent if a comment is edited after being posted
	 */
	public function onAfterWrite() {
		parent::onAfterWrite();

		$parentClass = $this->owner->BaseClass;
		$parentID = $this->owner->ParentID;

		// We only want to notify people if certain conditions are met:
		// - The comment has passed moderation (aka. if required, it has been approved by an admin)
		// - We are either seeing the Comment for the first time, or it has just passed moderation by an admin
		if($this->shouldSendUserNotificationEmails()) {
			if(ClassInfo::exists($parentClass)) {
				$commentParent = $parentClass::get()->byID($parentID);

				// Get all comments attached to this page, which we have to do manually as the has_one relationship is
				// 'faked' by the Comment class (because it can be attached to multiple parent classes).
				if($commentParent) {
					$comments = Comment::get()->filter(array(
						'BaseClass'       => $parentClass,
						'ParentID'        => $parentID,
						'NotifyOfUpdates' => true
					));

					// If we have comments, iterate over them to build a unique list of all email addresses to notify
					if($comments) {
						$emailList = array();

						foreach($comments as $c) {
							$author = $c->Author();

							if($author) {
								if(!in_array($author->Email, $emailList)) {
									$emailList[] = $author->Email;
								}
							}
						}

						// Send an email to everyone in the list
						if(sizeof($emailList) > 0) {
							foreach($emailList as $emailAddress) {
								$email = new Email();
								$email->setSubject('New Comment on "' . $commentParent->dbObject('Title')->XML() . '"');
								$email->setFrom(Email::getAdminEmail());
								$email->setTo($emailAddress);
								$email->populateTemplate($this->owner);

								$email->send();
							}
						}
					}
				}
			}
		}
	}

	private function shouldSendUserNotificationEmails() {
		$changedFields = $this->owner->getChangedFields();

		return
			$changedFields &&
			(
				// New record, automatically moderated as moderation is not enabled for this site
				(
					isset($changedFields['ID']) &&
					isset($changedFields['Moderated']) &&
					$changedFields['ID']['before'] == 0 &&
					$changedFields['Moderated']['after'] === true
				)
					||
				// Existing record, moderation has just been set - meaning it has been approved by an admin
				(
					isset($changedFields['Moderated']) &&
					$changedFields['Moderated']['before'] == false &&
					$changedFields['Moderated']['after'] === true
				)
			);
	}
}