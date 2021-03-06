<?php
class SpecialRequestWikiQueue extends SpecialPage {
	function __construct() {
		parent::__construct( 'RequestWikiQueue' );
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$out = $this->getOutput();
		$this->setHeaders();

		if ( is_null( $par ) || $par === '' ) {
			$this->doPagerStuff();
		} else {
			$this->lookupRequest( $par );
		}
	}

	function doPagerStuff() {
		$request = $this->getRequest();
                $out = $this->getOutput();

		$localpage = $this->getPageTitle()->getLocalUrl();
		$searchConds = false;

		$type = $request->getVal( 'rwqSearchtype' );
		$target = $request->getVal( 'target' );
		$year = $request->getIntOrNull( 'year' );
		$month = $request->getIntOrNull( 'month' );
		$search = $request->getVal( 'rwqSearch' );

		if ( $type === 'requester' ) {
			$user = User::newFromName( $search );
			if ( !$user || !$user->getID() ) {
				$out->addWikiMsg( 'requestwikiqueue-usernotfound' );
			} else {
				$searchConds = array( 'cw_user' => $user->getID() );
			}
		} elseif ( $type === 'sitename' ) {
			$searchConds = array( 'cw_sitename' => $search );
		} elseif ( $type === 'dbname' ) {
			$searchConds = array( 'cw_dbname' => $search );
		} elseif ( $type === 'status' ) {
			$searchConds = array( 'cw_status' => $search );
		}

		$selecttypeform = "<select name=\"rwqSearchtype\"><option value=\"requester\">requester</option><option value=\"sitename\">sitename</option><option value=\"status\">status</option><option value=\"dbname\">dbname</option></select>";

		$form = Xml::openElement( 'form', array( 'action' => $localpage, 'method' => 'get' ) );
                $form .= '<fieldset><legend>' . $this->msg( 'requestwikiqueue-searchrequest' )->escaped() . '</legend>';
                $form .= Xml::openElement( 'table' );
		# TODO: Should be escaped against HTML, but should NOT escape $selecttypeform
		$form .= '<tr><td>Find wiki requests where the ' . $selecttypeform . ' is ';
		$form .= Xml::input( 'rwqSearch', 40, '' ) . '</td></tr>';
		$form .= '<tr><td>' . Xml::dateMenu( $year, $month ) . '</td>';
		$form .= '<td>' . Xml::submitButton( $this->msg( 'requestwikiqueue-searchbutton' )->escaped() ) . '</td></tr>';
                $form .= Xml::closeElement( 'table' );
                $form .= '</fieldset>';
		$form .= Xml::closeElement( 'form' );

		$out->addHTML( $form );

		$pager = new RequestWikiQueuePager( $this, $searchConds, $year, $month );
		$out->addHTML(
			$pager->getNavigationBar() .
			$pager->getBody() .
			$pager->getNavigationBar()
		);
	}

	function lookupRequest( $par ) {
		global $wgOut;

		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->selectRow( 'cw_requests',
			array(
				'cw_user',
				'cw_comment',
				'cw_dbname',
				'cw_language',
				'cw_private',
				'cw_sitename',
				'cw_status',
				'cw_status_comment',
				'cw_status_comment_user',
				'cw_status_comment_timestamp',
				'cw_timestamp',
				'cw_url',
				'cw_custom',
				'cw_category'
			),
			array(
				'cw_id' => $par
			),
			__METHOD__,
			array()
		);

		if ( !$res ) {
			$this->getOutput()->addWikiMsg( 'requestwikiqueue-requestnotfound' );
			return false;
		}

		$private = $res->cw_private == 0 ? 'No' : 'Yes';

		if ( $res->cw_status === 'inreview' ) {
			$status = 'In review';
		} else {
			$status = ucfirst( $res->cw_status );
		}

		$user = User::newFromId( $res->cw_user );

		$createwikiparams = array(
			'cwRequester' => $user->getName(),
			'cwLanguage' => $res->cw_language,
			'cwSitename' => $res->cw_sitename,
			'cwCategory' => $res->cw_category
		);

		if ( $res->cw_private != 0 ) {
			$createwikiparams['cwPrivate'] = 1;
		}

		if ( $res->cw_dbname != 'NULL' ) {
			$createwikiparams['cwDBname'] = $res->cw_dbname;
		}

		if ( $this->getUser()->isAllowed( 'createwiki' ) ) {
			$columnamount = 9;
		} else {
			$columnamount = 8;
		}

		$comments = $dbr->select( 'cw_comments', array( 'cw_id', 'cw_comment', 'cw_comment_user', 'cw_comment_timestamp' ), array( 'cw_id' => $par ), __METHOD__, array( 'ORDER BY' => 'cw_comment_timestamp DESC' ) );

		$localpage = $this->getPageTitle()->getLocalUrl() . "/$par";
		$form = Xml::openElement( 'form', array( 'action' => $localpage, 'method' => 'post' ) );
		$form .= '<fieldset><legend>' . $this->msg( 'requestwikiqueue-view' )->escaped() . '</legend>';
		$form .= Xml::openElement( 'table', array( 'class' => 'wikitable' ) );
		$form .= '<tr><th colspan="' . $columnamount . '">Wiki request #' . $par. ' by ' . Linker::userLink( $res->cw_user, User::newFromId( $res->cw_user )->getName() ) . ' at ' . $this->getLanguage()->timeanddate( $res->cw_timestamp, true ) . '</th></tr>';
		$form .= '<tr>';
		foreach ( array( 'sitename', 'requester', 'url', 'custom', 'language', 'private', 'status', 'edit' ) as $label ) {
			$form .= '<th>' . $this->msg( 'requestwikiqueue-request-label-' . $label )->escaped() . '</th>';
		}
		if ( $this->getUser()->isAllowed( 'createwiki' ) ) {
			$form .= '<th>' . $this->msg( 'requestwikiqueue-request-label-toolbox' )->escaped() . '</th>';
		}
		$form .= '</tr>';
		$form .= '<tr><td>' . htmlspecialchars( $res->cw_sitename ) . '</td>';
		$form .= '<td>' . htmlspecialchars( $user->getName() ) . Linker::userToolLinks( $res->cw_user, $user->getName() ) . '</td>';
		$form .= '<td>' . htmlspecialchars( $res->cw_url ) . '</td>';
		$form .= '<td>' . htmlspecialchars( $res->cw_custom ) . '</td>';
		$form .= '<td>' . htmlspecialchars( $res->cw_language ) . '</td>';
		$form .= '<td>' . $private . '</td>';
		$form .= '<td>' . $status . '</td>';
		$form .= '<td>' . Linker::linkKnown( SpecialPage::getTitleFor( 'RequestWikiEdit', $par ), $this->msg( 'requestwikiqueue-request-label-edit-wiki' )->escaped() ) . '</td>';
		if ( $this->getUser()->isAllowed( 'createwiki' ) ) {
			$form .= '<td>' . Linker::link( Title::newFromText( 'Special:CreateWiki' ), $this->msg( 'requestwikiqueue-request-label-create-wiki' )->escaped(), array(), $createwikiparams ) . '</td>';
		}
		$form .= '</tr>';
		$form .= '<tr><th colspan="' . $columnamount . '">' . $this->msg( 'requestwikiqueue-request-header-requestercomment' )->escaped() . '</th></tr>';
		$form .= '<tr><td colspan="' . $columnamount . '">' . htmlspecialchars( $res->cw_comment ) . '</td></tr>';

		foreach( $comments as $comment ) {
			$form .= '<tr><th colspan="' . $columnamount . '">' . $this->msg( 'requestwikiqueue-request-header-wikicreatorcomment-withtimestamp' )->rawParams( Linker::userLink( User::newFromId( $comment->cw_comment_user )->getId(), User::newFromId( $comment->cw_comment_user)->getName() ) )->params( $this->getLanguage()->timeanddate( $comment->cw_comment_timestamp, true ) )->escaped() . '</th></tr>';
			$form .= '<tr><td colspan="' . $columnamount . '">' .  $wgOut->parse( htmlspecialchars( $comment->cw_comment ) ) . '</td></tr>';
		}
		if ( $this->getUser()->isAllowed( 'createwiki' ) ) {
			$form .= '<tr><th colspan="' . $columnamount . '">' . $this->msg( 'requestwikiqueue-request-status' )->escaped() . '</th></tr>';
			$form .= '<tr><td colspan="' . $columnamount . '">' . $this->msg( 'requestwikiqueue-request-label-comment' )->escaped() . ' ' . Xml::input( 'rwqStatusComment', 45, '', array( 'required' => '' ) ) . ' ';
			$form .= $this->msg( 'requestwikiqueue-request-label-status-colon' )->escaped() . ' ' . Xml::radioLabel( $this->msg( 'requestwikiqueue-request-label-inreview' )->escaped(), 'rwqStatus', 'inreview', '', true ) . Xml::radioLabel( $this->msg( 'requestwikiqueue-request-label-approved' )->escaped(), 'rwqStatus', 'approved', '', false ) . Xml::radioLabel( $this->msg( 'requestwikiqueue-request-label-declined' )->escaped(), 'rwqStatus', 'declined', '', false ) . ' ';
			$form .= Xml::submitButton( 'Submit' ) . '</td></tr>';

		}
                $form .= Xml::closeElement( 'table' );
                $form .= '</fieldset>';
		$form .= Xml::closeElement( 'form' );

		$this->getOutput()->addHTML( $form );

		if ( $this->getRequest()->wasPosted() ) {
			$this->processRequestStatusChanges( $par );
		}
	}

	function processRequestStatusChanges( $id ) {
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( !$user->isAllowed( 'createwiki' ) ) {
			throw new MWException( 'User without createwiki right tried to modify wiki creator comment' );
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'cw_requests',
			array(
				'cw_status' => $request->getVal( 'rwqStatus' )
			),
			array(
				'cw_id' => $id
			),
			__METHOD__
		);
		$dbw->insert( 'cw_comments',
			array(
				'cw_id' => $id,
				'cw_comment' => $request->getVal( 'rwqStatusComment' ),
				'cw_comment_timestamp' => $dbw->timestamp(),
				'cw_comment_user' => $user->getId()
			),
			__METHOD__
		);

		$this->getRequest()->response()->header( 'Refresh: 1;' );

		return true;
	}

	protected function getGroupName() {
		return 'wikimanage';
	}
}
