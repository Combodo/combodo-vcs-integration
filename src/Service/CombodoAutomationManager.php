<?php

namespace Combodo\iTop\VCSManagement\Service;

use Exception;

class CombodoAutomationManager
{
	/** @var CombodoAutomationManager|null Singleton */
	private static ?CombodoAutomationManager $oSingletonInstance = null;

	private GitHubAPIService $oGithubApiService;

	/**
	 * @throws Exception
	 */
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
		$normalizedReviews = $this->NormalizeReviews($reviews);
		$decisionData = $this->GetDecisionByUser($normalizedReviews);
		$stateByUser = $this->BuildStateByUser(
			$decisionData['decision_by_user'],
			$decisionData['has_commented_only']
		);

		return $this->ApplyPendingReviewersState($stateByUser, $pendingReviewers);
	}

	private function BuildStateByUser(array $decisionByUser, array $hasCommentedOnly): array
	{
		$stateByUser = [];

		foreach ($decisionByUser as $login => $state) {
			$stateByUser[$login] = $state;
		}

		foreach ($hasCommentedOnly as $login => $_) {
			if (!isset($stateByUser[$login])) {
				$stateByUser[$login] = 'COMMENTED';
			}
		}

		return $stateByUser;
	}

	private function ApplyPendingReviewersState(array $stateByUser, array $pendingReviewers): array
	{
		// ⚠️ PRIORITÉ ABSOLUE : une demande de review active écrase tout,
		// même une décision antérieure (approved / changes_requested)
		foreach ($pendingReviewers as $pendingReview) {
			$requestedReviewer = $pendingReview['requestedReviewer'] ?? null;
			if (!is_array($requestedReviewer)) {
				continue;
			}

			// login isn't set for copilot
			$pendingLogin = $requestedReviewer['login'] ?? null;
			if (is_string($pendingLogin) && $pendingLogin !== '') {
				$stateByUser[$pendingLogin] = 'PENDING';
			}
		}

		return $stateByUser;
	}

	private function GetDecisionByUser(array $normalizedReviews): array
	{
		$decisionByUser = [];
		$hasCommentedOnly = [];

		foreach ($normalizedReviews as $review) {
			$login = $review['login'];
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

		return [
			'decision_by_user' => $decisionByUser,
			'has_commented_only' => $hasCommentedOnly,
		];
	}

	private function NormalizeReviews(array $reviews): array
	{
		$normalizedReviews = [];
		$canSortBySubmittedAt = true;

		foreach ($reviews as $index => $review) {
			if (!is_array($review)) {
				continue;
			}

			$login = $review['author']['login'] ?? null;
			$state = $review['state'] ?? null;

			if (!is_string($login) || $login === '' || !is_string($state) || $state === '') {
				continue;
			}

			$submittedAt = $review['submittedAt'] ?? null;
			if (!is_string($submittedAt) || $submittedAt === '') {
				$canSortBySubmittedAt = false;
			}

			$normalizedReviews[] = [
				'login' => $login,
				'state' => $state,
				'submittedAt' => $submittedAt,
				'index' => $index,
			];
		}

		// If every review has a submittedAt timestamp, force chronological order.
		if ($canSortBySubmittedAt) {
			usort($normalizedReviews, static function (array $left, array $right): int {
				if ($left['submittedAt'] === $right['submittedAt']) {
					return $left['index'] <=> $right['index'];
				}

				return $left['submittedAt'] <=> $right['submittedAt'];
			});
		}

		return $normalizedReviews;
	}

	public function GetReviewStateCounter(array $stateByUser): array
	{
		$stateCounter = [];

		foreach ($stateByUser as $state) {
			if (!isset($stateCounter[$state])) {
				$stateCounter[$state] = 0;
			}

			$stateCounter[$state]++;
		}

		return $stateCounter;
	}

	public function UpdateWebhookPRInformation(\VCSPullRequestAutomation $oAutomation, \DBObject $oWebhook, \UserRequest $oRequest, string $sObjectAttCode, array $aData): void
	{
		$aCurrentPullRequestReviewers = json_decode($oRequest->Get($sObjectAttCode), true);

		$sNumber = intval($aData['number']);
		$sOwner = $aData['base']['repo']['owner']['login'];
		$sRepoName = $aData['base']['repo']['name'];

		$aPendingReviewers = $this->oGithubApiService->GetPullRequestPendingReviewerGraphQL($oWebhook, $sOwner, $sRepoName, $sNumber);
		$aReviews = $this->oGithubApiService->GetPullRequestReviewGraphQL($oWebhook, $sOwner, $sRepoName, $sNumber);

		$sDecision = $aReviews['data']['repository']['pullRequest']['reviewDecision'];
		$aStateByUser = $this->GetReviewStatePerUser($aReviews['data']['repository']['pullRequest']['reviews']['nodes'], $aPendingReviewers['data']['repository']['pullRequest']['reviewRequests']['nodes']);
		$aStatesCounter = $this->GetReviewStateCounter($aStateByUser);

		$aCurrentPullRequestReviewers[$aData['id']] = [
			'number' => $aData['number'],
			'merged_at' => $aData['merged_at'],
			'draft' => $aData['draft'],
			'state' => $aData['state'],
			'html_url' => $aData['html_url'],
			'repo_name' => $aData['base']['repo']['name'],
			'base.ref' => $aData['base']['ref'],
			'decision' => $sDecision,
			'state_by_user' => $aStateByUser,
			'state_counter' => $aStatesCounter,
		];

		$oRequest->Set($sObjectAttCode, json_encode($aCurrentPullRequestReviewers));
		$oRequest->DBUpdate();
	}

}
