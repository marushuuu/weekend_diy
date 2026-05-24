<?php
/**
 * SEO記事インポートスクリプト
 *
 * 使い方:
 *   WordPress ルートに配置 → ブラウザで /import-articles.php?key=YOUR_SECRET を開く
 *   実行後は必ず削除する
 *
 * 例: https://example.com/import-articles.php?key=minnanokogu2026
 */

define( 'SHORTINIT', false );

// WordPress をロード
$wp_root = dirname( __FILE__, 4 ); // plugin dir → wp-content/plugins → wp-content → wp-root
if ( file_exists( $wp_root . '/wp-load.php' ) ) {
    require_once $wp_root . '/wp-load.php';
} else {
    die( 'wp-load.php が見つかりません。このファイルを WordPress プラグインディレクトリ内に配置してください。' );
}

// 簡易認証
$secret = 'minnanokogu2026';
if ( ( $_GET['key'] ?? '' ) !== $secret ) {
    http_response_code( 403 );
    die( '認証エラー: ?key=YOUR_SECRET を URL に追加してください。' );
}

if ( ! current_user_can( 'manage_options' ) && ! defined( 'WP_CLI' ) ) {
    // ブラウザからアクセス時は管理者ログインを要求
    auth_redirect();
}

header( 'Content-Type: text/html; charset=utf-8' );

// ─── 記事データ ───────────────────────────────────────────────────────────────

$articles = [
    [
        'title'    => 'インパクトドライバーとは？電気ドリルとの違いをわかりやすく解説',
        'slug'     => 'impact-driver-toha-denki-drill-chigai',
        'category' => '工具の基礎知識',
        'content'  => <<<'HTML'
<h2>インパクトドライバーって何？</h2>
<p>インパクトドライバーは、ネジや木ネジを素早く・強力に締める電動工具です。DIYをこれから始める人にとって、最初に揃えたい工具のひとつです。</p>
<p>ホームセンターや工具売り場で見かけることも多いですが、「電気ドリル（ドリルドライバー）」との違いがわからず、どちらを選べばいいか迷う方も多いはず。この記事では、その違いをわかりやすく説明します。</p>

<h2>インパクトドライバーと電気ドリルの違い</h2>
<table>
<thead><tr><th>比較項目</th><th>インパクトドライバー</th><th>電気ドリル（ドリルドライバー）</th></tr></thead>
<tbody>
<tr><td>締め付けトルク</td><td>非常に強い（100〜200 N·m 程度）</td><td>中程度（50 N·m 程度まで）</td></tr>
<tr><td>打撃機構</td><td>あり（回転＋打撃）</td><td>なし（回転のみ）</td></tr>
<tr><td>穴あけ</td><td>ビット次第で可能</td><td>得意（メイン用途）</td></tr>
<tr><td>主な用途</td><td>ネジ締め・ボルト締め</td><td>穴あけ・軽いネジ締め</td></tr>
<tr><td>手への振動</td><td>少ない</td><td>やや多い</td></tr>
<tr><td>価格帯</td><td>1万〜3万円台</td><td>5千〜2万円台</td></tr>
</tbody>
</table>

<h3>インパクトドライバーの特徴：「打撃」にある</h3>
<p>インパクトドライバーの最大の特徴は、<strong>回転しながら同時に打撃（インパクト）を加える</strong>仕組みです。ネジが固くて止まりそうになる瞬間に、内部のハンマーが「カカカカ」と叩き続け、強引に締め込みます。</p>
<p>この仕組みのおかげで：</p>
<ul>
<li><strong>太い木ネジも楽に締められる</strong></li>
<li><strong>手が疲れにくい</strong></li>
<li><strong>締め付け速度が速い</strong></li>
</ul>
<p>という特徴があります。</p>

<h3>電気ドリルの特徴：穴あけが得意</h3>
<p>電気ドリル（ドリルドライバー）は、主に<strong>穴を開ける</strong>ための工具です。トルク（締め付け力）の調整機能がついており、ネジを締めることもできますが、太いネジや硬い材料は苦手です。</p>

<h2>こんな用途ならインパクトドライバー</h2>
<ul>
<li>ウッドデッキの組み立て</li>
<li>棚・収納の DIY</li>
<li>フェンス・カーポートの設置</li>
<li>家具の組み立て・解体</li>
</ul>
<p><strong>太い木ネジやコーススレッドを大量に締める作業</strong>には、インパクトドライバーが圧倒的に向いています。</p>

<h2>こんな用途なら電気ドリル</h2>
<ul>
<li>タイルや金属に穴を開けたい</li>
<li>小さなネジを精密に締めたい</li>
<li>壁にアンカーを打ち込みたい</li>
</ul>

<h2>DIY初心者にはどちらがおすすめ？</h2>
<p><strong>ウッドデッキや棚など木材を使う DIY が目的なら、インパクトドライバー一択です。</strong></p>
<p>理由はシンプルで、木材のネジ締めにおいては電気ドリルより圧倒的に速く・楽に作業できるからです。DIY初心者ほど、パワーのある工具のほうが失敗が少なくなります。</p>

<h2>買うのかレンタルか？</h2>
<p>インパクトドライバーは1万5千円〜3万円程度が相場です。年に数回しか使わないなら、購入よりも<strong>レンタルのほうがコストを抑えられます</strong>。</p>
<p>当サービスでは HiKOKI の 18V インパクトドライバー（最大トルク 177 N·m）をバッテリー2個・急速充電器付きで貸し出しています。</p>
<p><strong>→ <a href="/products/impact-driver/">インパクトドライバーのレンタル詳細を見る</a></strong></p>

<h2>まとめ</h2>
<ul>
<li>インパクトドライバーは「回転＋打撃」でネジを強力に締める工具</li>
<li>電気ドリルは「穴あけ」が得意</li>
<li>木材 DIY ならインパクトドライバーが断然おすすめ</li>
<li>年数回の使用ならレンタルが賢い選択</li>
</ul>
HTML,
    ],
    [
        'title'    => 'インパクトドライバーはレンタルと購入どちらがお得？使用頻度別に徹底比較',
        'slug'     => 'impact-driver-rental-vs-purchase',
        'category' => 'レンタル活用術',
        'content'  => <<<'HTML'
<h2>「買うべき？借りるべき？」DIYerの悩みを解決</h2>
<p>インパクトドライバーの購入を検討していると、こんな疑問が出てきませんか？</p>
<blockquote><p>「1〜2回しか使わないけど、買ったほうがいい？レンタルでいい？」</p></blockquote>
<p>結論から言うと、<strong>年間の使用頻度によって答えは変わります</strong>。この記事では使用頻度別にコストを比較し、どちらが賢い選択かを明確にします。</p>

<h2>コスト比較：レンタル vs 購入</h2>
<h3>購入の場合</h3>
<table>
<thead><tr><th>グレード</th><th>価格帯</th><th>特徴</th></tr></thead>
<tbody>
<tr><td>エントリー（14.4V）</td><td>8,000〜15,000円</td><td>トルク弱め、バッテリー別売りも</td></tr>
<tr><td>ミドル（18V）</td><td>15,000〜25,000円</td><td>DIY・本格作業に対応</td></tr>
<tr><td>プロ仕様（18V以上）</td><td>30,000〜50,000円</td><td>業務用レベル</td></tr>
</tbody>
</table>
<p>購入時のランニングコスト（5年使用の場合）：</p>
<ul>
<li>バッテリー交換：3〜4年で1個 約5,000〜8,000円</li>
<li>消耗品（ビット等）：年1,000〜2,000円</li>
</ul>
<p>→ <strong>5年総コストの目安：25,000〜40,000円</strong></p>

<h3>レンタルの場合（当サービス）</h3>
<table>
<thead><tr><th>期間</th><th>料金</th></tr></thead>
<tbody>
<tr><td>1週間</td><td>4,900円</td></tr>
<tr><td>2週目以降</td><td>3,430円/週（30%OFF）</td></tr>
<tr><td>2週間合計</td><td>8,330円</td></tr>
<tr><td>3週間合計</td><td>11,760円</td></tr>
</tbody>
</table>
<p>送料：3,000円以上のご注文で無料（北海道・沖縄・離島除く）</p>

<h2>使用頻度別おすすめ判断チャート</h2>
<h3>年1〜2回しか使わない → <strong>レンタルが断然お得</strong></h3>
<p>例：ウッドデッキを作る、棚を数本作る、引越し後に家具を組み立てる</p>
<ul>
<li>1週間レンタル費用: <strong>4,900円</strong></li>
<li>工具の保管スペース不要</li>
<li>最新モデルをいつでも使える</li>
<li>メンテナンス不要</li>
</ul>
<p>購入した場合、5年で2〜3回しか使わなければ1回あたり8,000〜15,000円のコストになります。</p>

<h3>年3〜5回使う → <strong>どちらとも言えない</strong></h3>
<p>この頻度なら購入も視野に入ります。ただし保管スペースやバッテリー管理の手間を考えると、まずはレンタルで試してから購入を判断するのがおすすめです。</p>

<h3>週1回以上・月複数回使う → <strong>購入がお得</strong></h3>
<p>本格的に DIY を趣味にしていたり、仕事で使う場合は購入一択です。</p>

<h2>レンタルならではのメリット</h2>
<h3>1. プロ仕様の工具をお試し価格で使える</h3>
<p>当サービスでレンタルしている HiKOKI WH18DDL2 は、最大トルク 177 N·m のプログレードモデル。市販価格は2万円以上ですが、<strong>1週間4,900円</strong>で使えます。</p>

<h3>2. 工具を保管しなくていい</h3>
<p>マンション住まいや収納スペースが少ない方に特に喜ばれています。使わない工具が押し入れを占領することもありません。</p>

<h3>3. バッテリーの劣化を気にしなくていい</h3>
<p>電動工具のバッテリーは3〜4年で劣化します。購入した場合は交換費用も必要ですが、レンタルなら常に満充電・メンテナンス済みの状態でお届けします。</p>

<h3>4. 引越しや大型DIY前の「お試し」に最適</h3>
<p>工具を買う前に「自分に合うか」「この作業に必要か」を確認できます。</p>

<h2>レンタルのデメリットと対策</h2>
<table>
<thead><tr><th>デメリット</th><th>対策</th></tr></thead>
<tbody>
<tr><td>返却が手間</td><td>ゆうパックの着払いで送るだけ（伝票同梱）</td></tr>
<tr><td>急に必要になっても対応できない場合がある</td><td>最短翌日お届け対応</td></tr>
<tr><td>長期使用だとコスト高</td><td>2週目以降30%OFFで対応</td></tr>
</tbody>
</table>

<h2>結論：初めてのDIYはまずレンタルで試そう</h2>
<p><strong>初めてインパクトドライバーを使うなら、レンタルで1週間試すのが最もリスクが低い選択です。</strong></p>
<ul>
<li>自分の DIY スタイルに合うか確認できる</li>
<li>プロ仕様の工具を低コストで体験できる</li>
<li>気に入ったら同じモデルを購入する判断材料になる</li>
</ul>
<p><strong>→ <a href="/rental/">インパクトドライバーを1週間レンタルしてみる（¥4,900〜）</a></strong></p>
HTML,
    ],
    [
        'title'    => 'DIY初心者でも作れる！棚の作り方【完全手順ガイド】',
        'slug'     => 'diy-shelf-beginner-complete-guide',
        'category' => 'DIY ハウツー',
        'content'  => <<<'HTML'
<h2>はじめに：棚 DIY は初心者に最適なファーストプロジェクト</h2>
<p>「DIY をやってみたいけど、何から始めればいい？」</p>
<p>そう思っている方に最初におすすめするのが<strong>棚の DIY</strong>です。理由は3つ：</p>
<ol>
<li><strong>材料が安い</strong>（1×4材やSPF材はホームセンターで1本300〜500円）</li>
<li><strong>失敗しても大きなダメージがない</strong></li>
<li><strong>完成したときの満足感が大きい</strong></li>
</ol>
<p>この記事では、工具の選び方から完成まで、丁寧に解説します。</p>

<h2>必要な工具と材料</h2>
<h3>工具リスト</h3>
<table>
<thead><tr><th>工具</th><th>用途</th><th>入手方法</th></tr></thead>
<tbody>
<tr><td><strong>インパクトドライバー</strong></td><td>ネジ締め（メイン工具）</td><td>レンタル推奨</td></tr>
<tr><td>メジャー</td><td>寸法の計測</td><td>持参 or 購入（500円〜）</td></tr>
<tr><td>さしがね（直角定規）</td><td>直角を確認</td><td>購入（300円〜）</td></tr>
<tr><td>鉛筆</td><td>墨付け（印つけ）</td><td>家にあるもので OK</td></tr>
<tr><td>ヤスリ（#120・#240）</td><td>木材の面取り</td><td>購入（200円〜）</td></tr>
<tr><td>ドライバービット</td><td>インパクトに装着</td><td>レンタルセットに付属</td></tr>
</tbody>
</table>
<p><strong>インパクトドライバーはレンタルが賢い選択です。</strong><br>
買うと1万5千円〜3万円かかりますが、レンタルなら1週間4,900円。棚1〜2本作るだけなら断然レンタルがお得です。</p>
<p>→ <a href="/rental/">インパクトドライバーをレンタルする</a></p>

<h3>材料リスト（壁付け棚1枚の例）</h3>
<table>
<thead><tr><th>材料</th><th>サイズ目安</th><th>単価目安</th></tr></thead>
<tbody>
<tr><td>SPF 1×8材（棚板）</td><td>長さ900mm × 幅184mm</td><td>600円前後</td></tr>
<tr><td>SPF 2×4材（棚柱）</td><td>長さ1800mm</td><td>800円前後</td></tr>
<tr><td>コーススレッド（木ネジ）</td><td>65mm、16本程度</td><td>300円/箱</td></tr>
<tr><td>L字金具</td><td>4個</td><td>500円</td></tr>
<tr><td>壁用アンカー</td><td>石膏ボード用 4本</td><td>300円</td></tr>
<tr><td>木材用ニス or ペンキ</td><td>お好みで</td><td>500円〜</td></tr>
</tbody>
</table>
<p><strong>材料費の合計目安：約3,000〜5,000円</strong></p>

<h2>作業手順</h2>
<h3>Step 1：設計・寸法決め（所要時間：30分）</h3>
<p>まず「どんな棚を作るか」を決めます。</p>
<p><strong>決めること：</strong></p>
<ul>
<li>棚の幅・高さ・奥行き</li>
<li>棚板の枚数</li>
<li>壁に固定するか、独立して立てるか</li>
</ul>
<p>初心者には<strong>壁に取り付けるシンプルな1段棚</strong>から始めることをおすすめします。</p>
<p><strong>寸法の決め方のコツ：</strong></p>
<ul>
<li>棚板の幅は900mm以内にすると材料が無駄なく使える</li>
<li>奥行きは200〜300mmが使いやすい</li>
<li>ホームセンターの材料サイズに合わせて設計すると端材が出にくい</li>
</ul>

<h3>Step 2：木材の購入とカット（所要時間：1〜2時間）</h3>
<p>ホームセンターで木材を購入し、カットサービスを利用しましょう。</p>
<p><strong>ポイント：</strong></p>
<ul>
<li>ホームセンターの木材カットサービスは1カット50〜100円程度</li>
<li>自分でカットするより精度が高く、初心者にはおすすめ</li>
<li>設計図（メモでOK）を持参して正確な寸法を伝える</li>
</ul>
<p>カットしてもらった木材の切り口は、#120のヤスリで整えてから作業を始めましょう。</p>

<h3>Step 3：木材の仕上げ（所要時間：30〜60分）</h3>
<p><strong>やすりがけの手順：</strong></p>
<ol>
<li>#120（粗め）で全体をやすりがけ</li>
<li>#240（細め）で表面を滑らかに</li>
<li>木くずを布で拭き取る</li>
</ol>
<p>塗装する場合はこのタイミングで行います。ニスや水性ペンキをハケで塗り、乾燥させてから組み立てに進みます。</p>

<h3>Step 4：ネジ下穴あけ（所要時間：30分）</h3>
<p>いきなりネジを締めると木材が割れることがあります。インパクトドライバーに<strong>ドリルビット</strong>を装着し、ネジより一回り細い下穴を開けましょう。</p>
<p><strong>下穴のサイズ目安：</strong></p>
<ul>
<li>コーススレッド 65mm → 下穴 3.5mm 程度</li>
</ul>
<p><strong>ポイント：</strong> インパクトドライバーのモードを「ソフト」に設定すると、初心者でも失敗しにくくなります。</p>

<h3>Step 5：組み立て（所要時間：1〜2時間）</h3>
<p>いよいよ組み立てです。インパクトドライバーにプラスビット（No.2）を装着して作業開始。</p>
<p><strong>組み立ての順番（L字型壁付け棚の場合）：</strong></p>
<ol>
<li>棚柱（縦材）を2本、壁の取り付け位置に合わせて仮置き</li>
<li>棚板を棚柱の間に置いて位置を決める</li>
<li>L字金具で棚板と棚柱を固定（コーススレッドで締める）</li>
<li>棚柱を壁に固定（石膏ボード用アンカーを使用）</li>
</ol>
<p><strong>インパクトドライバーの使い方のコツ：</strong></p>
<ul>
<li>ネジを手で仮留めしてからドライバーで本締め</li>
<li>最初はゆっくりトリガーを引いて、慣れたら速度を上げる</li>
<li>ネジが締まり切る直前に速度を落とすと、なめる（ネジ頭を傷つける）のを防げる</li>
</ul>

<h3>Step 6：水平確認と壁への固定（所要時間：30分）</h3>
<p>棚板が水平かどうかを確認してから壁に固定します。</p>
<p><strong>水平確認の方法：</strong></p>
<ul>
<li>スマートフォンの水準器アプリを棚板の上に置く（無料で使える）</li>
<li>ペットボトルに水を入れて置いて傾きを見る</li>
</ul>
<p>水平が確認できたら、壁側のネジを本締めして完成です。</p>

<h2>完成！仕上げのチェックリスト</h2>
<ul>
<li>棚がぐらつかないか（全ネジを再確認）</li>
<li>棚板が水平か</li>
<li>ネジ頭が飛び出していないか</li>
<li>やすりがけ漏れがないか</li>
<li>壁アンカーがしっかり固定されているか</li>
</ul>

<h2>よくある失敗と対策</h2>
<table>
<thead><tr><th>失敗</th><th>原因</th><th>対策</th></tr></thead>
<tbody>
<tr><td>木材が割れた</td><td>下穴なしでネジを締めた</td><td>必ず下穴を開ける</td></tr>
<tr><td>ネジがなめた</td><td>締めすぎ or ビットが合っていない</td><td>適切なサイズのビットを使う</td></tr>
<tr><td>棚が傾いた</td><td>測定が甘かった</td><td>組み立て前に水平を確認する</td></tr>
<tr><td>ネジが途中で止まった</td><td>木材が硬い</td><td>インパクトドライバーのモードを「パワー」に切り替える</td></tr>
</tbody>
</table>

<h2>まとめ</h2>
<p>棚の DIY は、正しい手順と工具があれば初心者でも必ずできます。</p>
<p><strong>成功のポイントを3つ挙げると：</strong></p>
<ol>
<li>設計を丁寧に行い、寸法をホームセンターでカットしてもらう</li>
<li>下穴を開けてからネジを締める</li>
<li>インパクトドライバーを使って効率よく作業する</li>
</ol>
<p>工具（インパクトドライバー）をお持ちでない方は、ぜひレンタルをご活用ください。バッテリー2個・急速充電器・ケース付きで、すぐに作業を始めていただけます。</p>
<p><strong>→ <a href="/rental/">インパクトドライバーを1週間レンタルする（¥4,900〜）</a></strong></p>
HTML,
    ],
];

// ─── カテゴリを作成または取得 ─────────────────────────────────────────────────

function get_or_create_category( string $name ): int {
    $existing = get_term_by( 'name', $name, 'category' );
    if ( $existing ) return $existing->term_id;

    $result = wp_insert_term( $name, 'category' );
    return is_wp_error( $result ) ? 1 : $result['term_id'];
}

// ─── インポート実行 ───────────────────────────────────────────────────────────

$results = [];

foreach ( $articles as $article ) {
    $existing = get_page_by_path( $article['slug'], OBJECT, 'post' );
    if ( $existing ) {
        $results[] = [
            'title'  => $article['title'],
            'status' => 'スキップ（既に存在します）',
            'url'    => get_permalink( $existing->ID ),
        ];
        continue;
    }

    $cat_id  = get_or_create_category( $article['category'] );
    $post_id = wp_insert_post( [
        'post_title'     => $article['title'],
        'post_name'      => $article['slug'],
        'post_content'   => $article['content'],
        'post_status'    => 'publish',
        'post_type'      => 'post',
        'post_author'    => 1,
        'post_date'      => '2026-05-01 09:00:00',
        'post_date_gmt'  => '2026-05-01 00:00:00',
        'post_category'  => [ $cat_id ],
        'comment_status' => 'closed',
    ], true );

    if ( is_wp_error( $post_id ) ) {
        $results[] = [
            'title'  => $article['title'],
            'status' => '失敗: ' . $post_id->get_error_message(),
            'url'    => '',
        ];
    } else {
        $results[] = [
            'title'  => $article['title'],
            'status' => '作成しました',
            'url'    => get_permalink( $post_id ),
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>SEO記事インポート結果</title>
<style>
body{font-family:sans-serif;max-width:800px;margin:40px auto;padding:0 20px}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{border:1px solid #ccc;padding:10px;text-align:left}
th{background:#f5f5f5}
.ok{color:green;font-weight:bold}
.skip{color:#888}
.err{color:red;font-weight:bold}
</style>
</head>
<body>
<h1>SEO記事インポート結果</h1>
<table>
<thead><tr><th>記事タイトル</th><th>ステータス</th><th>URL</th></tr></thead>
<tbody>
<?php foreach ( $results as $r ) :
    $cls = str_contains( $r['status'], '作成' ) ? 'ok' : ( str_contains( $r['status'], 'スキップ' ) ? 'skip' : 'err' );
?>
<tr>
    <td><?php echo esc_html( $r['title'] ); ?></td>
    <td class="<?php echo $cls; ?>"><?php echo esc_html( $r['status'] ); ?></td>
    <td><?php if ( $r['url'] ) echo '<a href="' . esc_url( $r['url'] ) . '" target="_blank">' . esc_html( $r['url'] ) . '</a>'; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p style="margin-top:30px;color:red"><strong>⚠️ このファイルを実行したら必ず削除してください。</strong></p>
</body>
</html>
