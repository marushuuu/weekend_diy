# seo-article-writer スキル

検索意図ベースのSEO記事制作を一気通貫で行う、業種非依存のClaude Codeスキル。

## 構成
- `SKILL.md` … 汎用フロー本体（10フェーズ・厳守ルール）。業種に依存しない。
- `project.config.md` … プロジェクト固有設定（ドメイン・CTA・競合・トーン・ポリシー）。

## 新しいプロジェクトで使う手順
1. このフォルダ（seo-article-writer）を新プロジェクトのスキル置き場にコピーする。
   - Claude Code: `~/.claude/skills/` 配下、または各リポジトリの `skills/` 配下。
2. `project.config.md` を新プロジェクト用に書き換える（SKILL.md末尾のテンプレ参照）。
   - 最低限: domain / articles_dir / cta_base / competitors / quality_pass_line。
3. Claude Code で「この KW で SEO 記事を書いて」と指示する。
   スキルが自動で起動し、まず project.config.md を読んでから10フェーズを実行する。

## 設計思想
- SKILL.md（方法論）と project.config.md（固有設定）を分離しているため、
  別プロジェクトへは config の差し替えだけで移植できる。
- 公開・料金・規約に関わる判断は必ず人間承認（Human-in-the-loop）。

## このリポジトリ（weekend-diy）での実例
`project.config.md` が記入済みなので、そのまま現プロジェクトの記事制作に使える。
