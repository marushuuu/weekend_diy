# プロジェクト設定: みんなのレンタル工具（weekend-diy）

domain: https://weekend-diy.com
articles_dir: docs/articles
master_csv: docs/article-master.csv
cta_base: https://weekend-diy.com/
cta_pattern: /rental/?product_id={id}
blog_url_pattern: https://weekend-diy.com/blog/{slug}/

# 競合・参照
competitors: [rentool.jp, wecurly.com, rentaltry.com]
keyword_source: 競合3社のオーガニックKW CSV（SEMrush形式・/mnt/user-data/uploads/）

# 執筆設定
tone: DIY初心者向け・丁寧・結論先出し
accent_color: "E85A2B"
sub_color: "F7C948"
ink_color: "1F1D1A"
font: Noto Sans JP

# 品質・運用ポリシー
quality_pass_line: 80
primary_source_required: false   # 梱包/返送の一次情報注入は当面スキップ（運営判断）
author_block: false              # 運営者情報ブロックは保留中
auto_publish: false              # 公開・料金・規約は人間（Shu）承認必須

# KW判断基準
kd_max: 35
vol_min: 100

# 既知の固有教訓
lessons:
  - 「トリマー」Vol90,500はペット美容師/バリカン含む複合KW。木工用実効は推定10%前後。
    → 複合KWは検索意図を先に確認し実効Volを推定する。
  - 高優先の穴: 高圧洗浄機レンタル(Vol3,600/KD23)・コーナン工具レンタル(Vol2,900/KD18)。
  - カニバリ実績: 16・18統合、06をHC比較ハブ化（13・14へ内部リンク）、04⇄15を相互リンク。

# トピッククラスター（内部リンク設計の指針）
clusters:
  - インパクト/ドリル: [01,02,16,17,21,22]
  - 棚DIY: [03,07,08]
  - 穴あけ: [12,20]
  - ホームセンター比較: [06,13,14,05]
  - トリマー: [04,15]
