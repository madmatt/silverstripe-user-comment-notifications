<?php
/**
 * Class CommentingControllerUserNotificationsExtension
 * Extends the comment form to include a field that allows users to select whether or not they want to be notified when
 * further comments are made on that combination of the {@link Comment} db fields BaseClass and ParentID.
 *
 * @see Comment
 */
class CommentingControllerUserNotificationsExtension extends Extension
{
    public static $allowed_actions = array(
        'unsubscribenotification'
    );

    /**
     * Alter the comment form to add a checkbox to give users the ability to receive notifications when further
     * comments are posted. The {@link CommentingController::doPostComment()} method will take care of saving this
     * field for us, as it's part of the {@link Comment} DataObject
     *
     * @see CommentUserNotificationsExtension
     * @param Form $form The Form object used to render the comments form
     */
    public function alterCommentForm(Form $form)
    {
        $form->Fields()->insertAfter(
            CheckboxField::create(
                'NotifyOfUpdates',
                _t('CommentInterface.NOTIFYOFUPDATES', 'Please notify me about new comments posted here.')
            ),
            'Comment'
        );
    }

    /**
     * Uses $this->owner->request (a {@link SS_HTTPRequest} object) to determine which comment we want to unsubscribe
     * the member from. If the current user isn't logged in, or is logged in as a different user, then we send them to
     * the login screen.
     */
    public function unsubscribenotification()
    {
        $request = $this->owner->getRequest();

        $commentID = $request->param('ID');
        $member = Member::currentUser();

        if (!$commentID) {
            $this->owner->httpError(403);
            return;
        }

        $comment = Comment::get()->byID($commentID);

        if (!$comment) {
            $this->owner->httpError(403);
            return;
        }

        if (!$member || $member->ID != $comment->AuthorID) {
            return Security::permissionFailure(
                $this->owner,
                array(
                    'default' => _t(
                        'CommentingControllerUserNotificationsExtension.DEFAULTFAIL',
                        'You must login to unsubscribe.'
                    ),
                    'alreadyLoggedIn' => _t(
                        'CommentingControllerUserNotificationsExtension.ALREADYLOGGEDINFAIL',
                        'You must login as the correct user (the user who submitted the comment) to continue.'
                    ),
                    'logInAgain' => _t(
                        'CommentingControllerUserNotificationsExtension.LOGINAGAINFAIL',
                        'You have been logged out. If you would like to login again, enter your credentials below.'
                    )
                )
            );
        }

        // Currently logged in Member's ID matches the author of the comment, so we can unsubscribe them
        // We want to find all comments posted to this object by this author, and unsubscribe all of them.
        $allComments = Comment::get()->filter(array(
            'BaseClass'       => $comment->BaseClass,
            'ParentID'        => $comment->ParentID,
            'NotifyOfUpdates' => true
        ));

        foreach ($allComments as $c) {
            $c->NotifyOfUpdates = false;
            $c->write();
        }

        // This sets a session var that can be queried on the page that we redirect the user back to, so that we can
        // display a nice message to let the user know their unsubscription was successful.
        Session::set('CommentUserNotificationsUnsubscribed', '1');

        $this->owner->redirectBack();
    }
}
