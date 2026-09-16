<?php

namespace Combodo\iTop\VCSManagement\Service;

class CombodoAutomationManager
{
	/** @var CombodoAutomationManager|null Singleton */
	private static ?CombodoAutomationManager $oSingletonInstance = null;

	private GitHubAPIService $oGithubApiService;

	public function __construct()
	{
		$this->oGithubApiService = GitHubAPIService::GetInstance();
	}

	public static function GetInstance(): CombodoAutomationManager
	{
		if (is_null(self::$oSingletonInstance)) {
			self::$oSingletonInstance = new CombodoAutomationManager();
		}

		return self::$oSingletonInstance;
	}

	public function GetReviewStatePerUser(array $reviews, array $pendingReviewers): array
	{
		$decisionByUser = [];
		$hasCommentedOnly = [];

		foreach ($reviews as $review) {
			$login = $review['author']['login'];
			$state = $review['state'];

			if ($state === 'DISMISSED') {
				continue;
			}

			if ($state === 'COMMENTED') {
				$hasCommentedOnly[$login] = true;
				continue;
			}

			$decisionByUser[$login] = $state; // écrase avec la plus récente
		}

		$stateByUser = [];

		foreach ($decisionByUser as $login => $state) {
			$stateByUser[$login] = $state;
		}

		foreach ($hasCommentedOnly as $login => $_) {
			if (!isset($stateByUser[$login])) {
				$stateByUser[$login] = 'COMMENTED';
			}
		}

		// ⚠️ PRIORITÉ ABSOLUE : une demande de review active écrase tout,
		// même une décision antérieure (approved / changes_requested)
		foreach ($pendingReviewers as $pendingReview) {
			$stateByUser[$pendingReview['requestedReviewer']['login']] = 'PENDING';
		}

		return $stateByUser;
	}

	public function UpdateWebhookPRInformation(VCSPullRequestAutomation $oAutomation, UserRequest $oRequest, \DBObject $oWebhook, int $iPullRequestNumber, string $sOwner, string $sRepositoryName): void
	{
		\IssueLog::Error(__FILE__.'::'.__LINE__);
		$aPendingReviewers = $this->oGithubApiService->GetPullRequestPendingReviewerGraphQL($oWebhook, $sOwner, $sRepositoryName, $iPullRequestNumber);
		\IssueLog::Error(__FILE__.'::'.__LINE__);
		$aReviews = $this->oGithubApiService->GetPullRequestReviewGraphQL($oWebhook, $sOwner, $sRepositoryName, $iPullRequestNumber);
		\IssueLog::Error(__FILE__.'::'.__LINE__);
		$aStateByUser = $this->GetReviewStatePerUser($aReviews['data']['repository']['pullRequest']['reviews']['nodes'], $aPendingReviewers['data']['repository']['pullRequest']['reviewRequests']['nodes']);
		\IssueLog::Error(__FILE__.'::'.__LINE__);
		$oRequest->Set('pull_requests_reviewers', json_encode($aStateByUser));
		$oRequest->DBUpdate();
	}

}
