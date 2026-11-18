<?php
/* One field's threads (or the map's own, $threadField null), as the map page
   draws them and /mtasa/ajax/comment sends them back. Takes $threads,
   $threadField, $threadMapId, $threadAccountId, $threadIsAdmin, $threadSignedIn. */

$open = array_values(array_filter($threads, fn($thread) => $thread['resolved_at'] === null));
$resolved = array_values(array_filter($threads, fn($thread) => $thread['resolved_at'] !== null));

$comment = function ($row, $isReply) use ($threadAccountId, $threadIsAdmin) {
	$deleted = $row['deleted_at'] !== null;
	ob_start(); ?>
	<div class="mtasaComment<?= $isReply ? ' mtasaReply' : '' ?><?= $deleted ? ' mtasaCommentDeleted' : '' ?>" data-comment-id="<?= (int)$row['id'] ?>">
		<div class="mtasaCommentHead">
			<span class="mtasaCommentAuthor"><?= htmlspecialchars($row['author_name'] ?? t('comments.deletedAccount')) ?></span>
			<time class="mtasaCommentTime" datetime="<?= date('c', (int)$row['created_at']) ?>"><?= date('Y-m-d H:i', (int)$row['created_at']) ?></time>
			<?php if ($row['edited_at'] !== null && !$deleted): ?>
				<span class="mtasaCommentEdited" data-tooltip="<?= date('Y-m-d H:i', (int)$row['edited_at']) ?>"><?= t('comments.edited') ?></span>
			<?php endif ?>
		</div>
		<?php if ($deleted): ?>
			<p class="mtasaCommentBody"><?= t('comments.deleted') ?></p>
		<?php else: ?>
			<p class="mtasaCommentBody"><?= htmlspecialchars($row['body']) ?></p>
			<div class="mtasaCommentActions">
				<?php if (mtasaCanEditComment($row, $threadAccountId)): ?>
					<button type="button" class="mtasaLinkButton" data-comment-action="edit"><?= t('comments.edit') ?></button>
				<?php endif ?>
				<?php if (mtasaCanDeleteComment($row, $threadAccountId, $threadIsAdmin)): ?>
					<button type="button" class="mtasaLinkButton" data-comment-action="delete"><?= t('comments.delete') ?></button>
				<?php endif ?>
			</div>
		<?php endif ?>
	</div>
	<?php return ob_get_clean();
};

$thread = function ($row) use ($comment, $threadAccountId, $threadIsAdmin, $threadSignedIn) {
	$isResolved = $row['resolved_at'] !== null;
	ob_start(); ?>
	<div class="mtasaThread<?= $isResolved ? ' mtasaThreadResolved' : '' ?>" data-thread-id="<?= (int)$row['id'] ?>">
		<?= $comment($row, false) ?>
		<?php foreach ($row['replies'] as $reply): ?>
			<?= $comment($reply, true) ?>
		<?php endforeach ?>
		<div class="mtasaThreadFoot">
			<?php if ($threadSignedIn && !$isResolved): ?>
				<form class="mtasaCommentForm" data-parent="<?= (int)$row['id'] ?>">
					<textarea name="body" rows="2" maxlength="<?= MTASA_COMMENT_LIMIT ?>" placeholder="<?= htmlspecialchars(t('comments.replyPlaceholder')) ?>" aria-label="<?= htmlspecialchars(t('comments.reply')) ?>"></textarea>
					<button type="submit"><?= t('comments.reply') ?></button>
				</form>
			<?php endif ?>
			<?php if ($threadSignedIn && mtasaCanResolveThread($row, $threadAccountId, $threadIsAdmin)): ?>
				<button type="button" class="mtasaLinkButton" data-comment-action="<?= $isResolved ? 'reopen' : 'resolve' ?>"><?= t($isResolved ? 'comments.reopen' : 'comments.resolve') ?></button>
			<?php endif ?>
			<?php if ($isResolved): ?>
				<span class="mtasaResolvedBy"><?= htmlspecialchars(t('comments.resolvedBy', ['name' => $row['resolver_name'] ?? t('comments.deletedAccount')])) ?></span>
			<?php endif ?>
		</div>
	</div>
	<?php return ob_get_clean();
};
?>
<?php foreach ($open as $row): ?>
	<?= $thread($row) ?>
<?php endforeach ?>

<?php if ($resolved): ?>
	<details class="mtasaResolvedThreads">
		<summary><?= htmlspecialchars(t('comments.resolvedCount', ['count' => count($resolved)])) ?></summary>
		<?php foreach ($resolved as $row): ?>
			<?= $thread($row) ?>
		<?php endforeach ?>
	</details>
<?php endif ?>

<?php if ($threadSignedIn): ?>
	<form class="mtasaCommentForm mtasaNewThread">
		<textarea name="body" rows="2" maxlength="<?= MTASA_COMMENT_LIMIT ?>" placeholder="<?= htmlspecialchars(t($threadField === null ? 'comments.newGeneralPlaceholder' : 'comments.newPlaceholder')) ?>" aria-label="<?= htmlspecialchars(t('comments.start')) ?>"></textarea>
		<button type="submit"><?= t('comments.start') ?></button>
	</form>
<?php elseif (!$threads): ?>
	<p class="mtasaCommentHint"><a href="/mtasa/login"><?= t('comments.logInToComment') ?></a></p>
<?php else: ?>
	<p class="mtasaCommentHint"><a href="/mtasa/login"><?= t('comments.logInToReply') ?></a></p>
<?php endif ?>
