<?php
/**
 * Template Name: お問い合わせ
 */
defined( 'ABSPATH' ) || exit;

$sent  = false;
$error = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['kogu_contact_nonce'] ) ) {
	if ( ! wp_verify_nonce( $_POST['kogu_contact_nonce'], 'kogu_contact' ) ) {
		$error = '不正なリクエストです。もう一度お試しください。';
	} else {
		$name    = sanitize_text_field( $_POST['contact_name'] ?? '' );
		$email   = sanitize_email( $_POST['contact_email'] ?? '' );
		$subject = sanitize_text_field( $_POST['contact_subject'] ?? '' );
		$body    = sanitize_textarea_field( $_POST['contact_body'] ?? '' );

		if ( ! $name || ! $email || ! $subject || ! $body ) {
			$error = 'すべての項目を入力してください。';
		} elseif ( ! is_email( $email ) ) {
			$error = 'メールアドレスの形式が正しくありません。';
		} else {
			$to      = get_option( 'admin_email' );
			$headers = [
				'Content-Type: text/plain; charset=UTF-8',
				'Reply-To: ' . $name . ' <' . $email . '>',
			];
			$mail_body = "お名前：{$name}\nメール：{$email}\n件名：{$subject}\n\n{$body}";

			if ( wp_mail( $to, '[みんなの工具レンタル] ' . $subject, $mail_body, $headers ) ) {
				$sent = true;
			} else {
				$error = '送信に失敗しました。時間をおいて再度お試しください。';
			}
		}
	}
}

get_header();
?>

<div class="container">
	<div class="page-wrap">
		<h1 class="page-title">お問い合わせ</h1>
		<div class="page-card">

			<?php if ( $sent ) : ?>

				<div class="kogu-contact-thanks">
					<p class="kogu-contact-thanks-icon">✓</p>
					<p>お問い合わせを受け付けました。<br />通常2〜3営業日以内にご返信いたします。</p>
				</div>

			<?php else : ?>

				<p style="margin-bottom:24px;">ご不明な点やご質問は下記フォームよりお気軽にお問い合わせください。</p>

				<?php if ( $error ) : ?>
					<p class="kogu-contact-error"><?php echo esc_html( $error ); ?></p>
				<?php endif; ?>

				<form method="post" class="kogu-contact-form" novalidate>
					<?php wp_nonce_field( 'kogu_contact', 'kogu_contact_nonce' ); ?>

					<div class="kogu-contact-row">
						<label for="contact_name">お名前 <em>*</em></label>
						<input type="text" id="contact_name" name="contact_name" required
							value="<?php echo esc_attr( $_POST['contact_name'] ?? '' ); ?>"
							placeholder="例：山田 太郎" />
					</div>

					<div class="kogu-contact-row">
						<label for="contact_email">メールアドレス <em>*</em></label>
						<input type="email" id="contact_email" name="contact_email" required
							value="<?php echo esc_attr( $_POST['contact_email'] ?? '' ); ?>"
							placeholder="例：example@mail.com" />
					</div>

					<div class="kogu-contact-row">
						<label for="contact_subject">件名 <em>*</em></label>
						<select id="contact_subject" name="contact_subject" required>
							<option value="">選択してください</option>
							<option value="レンタルについて" <?php selected( ( $_POST['contact_subject'] ?? '' ), 'レンタルについて' ); ?>>レンタルについて</option>
							<option value="配送・返却について" <?php selected( ( $_POST['contact_subject'] ?? '' ), '配送・返却について' ); ?>>配送・返却について</option>
							<option value="料金・お支払いについて" <?php selected( ( $_POST['contact_subject'] ?? '' ), '料金・お支払いについて' ); ?>>料金・お支払いについて</option>
							<option value="その他" <?php selected( ( $_POST['contact_subject'] ?? '' ), 'その他' ); ?>>その他</option>
						</select>
					</div>

					<div class="kogu-contact-row">
						<label for="contact_body">お問い合わせ内容 <em>*</em></label>
						<textarea id="contact_body" name="contact_body" required rows="6"
							placeholder="お問い合わせ内容をご記入ください"><?php echo esc_textarea( $_POST['contact_body'] ?? '' ); ?></textarea>
					</div>

					<button type="submit" class="kogu-btn kogu-btn-primary kogu-contact-submit">送信する</button>
				</form>

			<?php endif; ?>

		</div>
	</div>
</div>

<?php get_footer(); ?>
