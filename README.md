# CODING AGENTS: READ THIS FIRST

This is a **handoff bundle** from Claude Design (claude.ai/design).

A user mocked up designs in HTML/CSS/JS using an AI design tool, then exported this bundle so a coding agent can implement the designs for real.

## What you should do — IMPORTANT

**Read the chat transcripts first.** There are 1 chat transcript(s) in `chats/`. The transcripts show the full back-and-forth between the user and the design assistant — they tell you **what the user actually wants** and **where they landed** after iterating. Don't skip them. The final HTML files are the output, but the chat is where the intent lives.

**Read `project/工具レンタルサイト ワイヤーフレーム.html` in full.** The user had this file open when they triggered the handoff, so it's almost certainly the primary design they want built. Read it top to bottom — don't skim. Then **follow its imports**: open every file it pulls in (shared components, CSS, scripts) so you understand how the pieces fit together before you start implementing.

**If anything is ambiguous, ask the user to confirm before you start implementing.** It's much cheaper to clarify scope up front than to build the wrong thing.

## About the design files

The design medium is **HTML/CSS/JS** — these are prototypes, not production code. Your job is to **recreate them pixel-perfectly** in whatever technology makes sense for the target codebase (React, Vue, native, whatever fits). Match the visual output; don't copy the prototype's internal structure unless it happens to fit.

**Don't render these files in a browser or take screenshots unless the user asks you to.** Everything you need — dimensions, colors, layout rules — is spelled out in the source. Read the HTML and CSS directly; a screenshot won't tell you anything they don't.

## Bundle contents

- `README.md` — this file
- `chats/` — conversation transcripts (read these!)
- `project/` — the `工具レンタルサイト構築` project files (HTML prototypes, assets, components)

---

## 本番サーバー構成（Xserver）

### アカウント情報
- **サーバー**: Xserver（sv17063）
- **アカウント**: `xs277376`
- **サイトURL**: https://weekend-diy.com

### ディレクトリ構成

```
/home/xs277376/
├── weekend_diy_deploy/          ← Git リポジトリ（このリポジトリのクローン）
│   └── wordpress-plugin/
│       └── kogu-rental/         ← ソースコード（ここを編集・git push）
│
└── weekend-diy.com/
    └── public_html/
        └── wp-content/
            └── plugins/
                └── kogu-rental/ ← WordPress が実際に読む場所（直接は編集しない）
```

**重要**: `weekend_diy_deploy/` と WordPress プラグインディレクトリは**別物**。
git push しただけでは本番に反映されない。必ず下記のデプロイ手順を実行すること。

### SSH接続コマンド

```bash
ssh -i ~/.ssh/xserver_key xs277376@sv17063.xserver.jp -p 10022
```

### デプロイ手順

コードを変更して git push した後、Xserver SSH で以下を実行：

```bash
cd /home/xs277376/weekend_diy_deploy && \
git pull origin work/task-6fSYE && \
cp -r wordpress-plugin/kogu-rental/* \
      /home/xs277376/weekend-diy.com/public_html/wp-content/plugins/kogu-rental/
```

#### バージョン確認（デプロイ成功の確認）
```bash
grep "KOGU_VERSION" /home/xs277376/weekend-diy.com/public_html/wp-content/plugins/kogu-rental/kogu-rental.php
```

#### デプロイ後
- ブラウザで **Ctrl+Shift+R**（強制リロード）
- キャッシュプラグインがある場合は WordPress 管理画面からキャッシュクリア

### WordPress 管理画面
- URL: https://weekend-diy.com/wp-admin/
- プラグイン: 工具レンタル（kogu-rental）

### 開発ブランチ
- 作業ブランチ: `work/task-6fSYE`
- リモート: `https://github.com/marushuuu/weekend_diy.git`
