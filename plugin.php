<?php

class pluginMemberAlbum extends Plugin
{
    private $uploadDir;
    private $metaFile;
    private $tokenFile;
    private $cssFile;

    public function init()
    {
        // プラグインフォルダ
        $this->tokenFile = $this->phpPath() . 'token.json';
        $this->cssFile = $this->domainPath() . 'css/MemberAlbum.css';

        // 画像保存先
        $this->uploadDir = PATH_CONTENT . 'uploads/MemberAlbum/';
        $this->albumDir = DOMAIN_BASE . 'bl-content/uploads/MemberAlbum/';
        $this->metaFile  = $this->uploadDir . 'meta.json';

        // uploads ディレクトリ作成（安全）
        if (!is_dir($this->uploadDir)) {
            @mkdir($this->uploadDir, 0755, true);
        }

        // meta.json 初期化
        if (!file_exists($this->metaFile)) {
            file_put_contents($this->metaFile, json_encode([]));
        }

        // token.json 初期化
        if (!file_exists($this->tokenFile)) {
            file_put_contents($this->tokenFile, json_encode([]));
        }
    }

    /* ---------------------------------------------------------
     * 管理画面フォーム
     * --------------------------------------------------------- */
    public function form()
    {
        $tokens = $this->loadTokens();

        $html  = '<div class="card">';
        $html .= '<div class="card-body">';

        $html .= '<h4 class="card-title">MemberAlbum トークン管理</h4>';
        $html .= '<p class="card-text">ユーザー名ごとに投稿トークンを発行します。</p>';

        // 入力フォーム
        $html .= '<div class="form-group">';
        $html .= '<label>ユーザー名</label>';
        $html .= '<input type="text" class="form-control" name="memberalbum_new_user" placeholder="ユーザー名を入力">';
        $html .= '</div>';

        $html .= '<button type="submit" class="btn btn-primary" name="memberalbum_generate_token">トークン発行</button>';

        if (!empty($tokens)) {
            $html .= '<hr>';
            $html .= '<h5>既存トークン一覧</h5>';
            $html .= '<ul class="list-group">';

            foreach ($tokens as $user => $token) {
                $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">';
                $html .= '<div>';
                $html .= '<strong>' . htmlspecialchars($user) . '</strong>';
                $html .= '<span class="badge badge-secondary ml-2">' . htmlspecialchars($token) . '</span>';
                $html .= '</div>';

                // 削除ボタン（Bootstrap）
                $html .= '<button type="submit" class="btn btn-danger btn-sm ml-2" name="memberalbum_delete_token" value="' . htmlspecialchars($user) . '">削除</button>';

                $html .= '</li>';
            }

            $html .= '</ul>';
        }

        // デバッグ情報（Bootstrap化）
        $html .= '<hr>';
        $html .= '<h5>Plugin Files Information</h5>';
        $html .= '<p><strong>uploadDir:</strong> ' . $this->uploadDir . '</p>';
        $html .= '<p><strong>metaFile:</strong> ' . $this->metaFile . '</p>';
        $html .= '<p><strong>tokenFile:</strong> ' . $this->tokenFile . '</p>';

        $html .= '</div>'; // card-body
        $html .= '</div>'; // card

        return $html;
    }

    /* ---------------------------------------------------------
     * 管理画面 POST
     * --------------------------------------------------------- */
    public function post()
    {
        if (isset($_POST['memberalbum_generate_token'])) {
            $user = trim($_POST['memberalbum_new_user']);
            if ($user === '') return;

            $tokens = $this->loadTokens();
            $tokens[$user] = $this->uuid();

            file_put_contents($this->tokenFile, json_encode($tokens, JSON_PRETTY_PRINT));
        }
        // トークン削除
        if (!empty($_POST['memberalbum_delete_token'])) {
        
            $userToDelete = trim($_POST['memberalbum_delete_token']);
            $tokens = $this->loadTokens();
        
            foreach ($tokens as $user => $token) {
                if ($user === $userToDelete) {
                    unset($tokens[$user]);
                    break;
                }
            }

            file_put_contents($this->tokenFile, json_encode($tokens, JSON_PRETTY_PRINT));

        }

    }

    /* ---------------------------------------------------------
     * フロント側 POST
     * --------------------------------------------------------- */
public function beforeAll()
{
    if (!isset($_POST['memberalbum_upload'])) return;

    $token = trim($_POST['memberalbum_token']);
    $tokens = $this->loadTokens();

    $user = array_search($token, $tokens, true);
    if ($user === false) {
        $this->redirectBack('トークンが無効です');
    }

    if (!isset($_FILES['memberalbum_image'])) {
        $this->redirectBack('ファイルがありません');
    }

    $tmp  = $_FILES['memberalbum_image']['tmp_name'];
    $mime = mime_content_type($tmp);

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed)) {
        $this->redirectBack('画像形式が不正です');
    }

    $ext = ($mime === 'image/png') ? '.png' :
           (($mime === 'image/webp') ? '.webp' : '.jpg');

    $filename = $this->uuid() . $ext;
    $savePath = $this->uploadDir . $filename;

    move_uploaded_file($tmp, $savePath);

    $meta = $this->loadMeta();
    $meta[$filename] = [
        'user' => $user,
        'time' => date('Y-m-d H:i:s')
    ];
    file_put_contents($this->metaFile, json_encode($meta, JSON_PRETTY_PRINT));

    // 投稿完了後に album ページへ戻す
    $this->redirectBack();
}

    /* ---------------------------------------------------------
     * Album ページに HTML を追加
     * --------------------------------------------------------- */
    public function pageBegin()
    {
        global $page;

        if ($page->slug() !== 'album') return;

        echo '<link rel="stylesheet" href="' . $this->cssFile . '">';
    }

    public function pageEnd()
    {
        global $page;

        if ($page->slug() !== 'album') return;

        echo $this->uploadForm();
        echo $this->gallery();
    }

    private function gallery()
    {
        $meta  = $this->loadMeta();
        $files = glob($this->uploadDir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE);

        $html  = '<div class="memberalbum-gallery">';

        foreach ($files as $file) {
            $name = basename($file);
            $user = $meta[$name]['user'] ?? 'anonymous';
            $posttime = $meta[$name]['time'] ?? '0000-00-00 00:00:00';
            $src  = $this->albumDir . $name;

            $html .= '<div class="memberalbum-item">';
            $html .= '<img src="' . $src . '">';
            $html .= '<p>' . $user . ' - ' . $posttime .'</p>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

private function uploadForm()
{
    if (isset($_GET['msg'])) {
        echo '<p class="notice">'.htmlspecialchars($_GET['msg']).'</p>';
    }

    return '

<details class="memberalbum">
  <summary class="memberalbum-toggle"></summary>

  <div class="memberalbum-panel">
    <form method="POST" enctype="multipart/form-data">
      <div class="form-group">
        <label>&#x1f5bc;</label>
        <input type="file" class="form-control" name="memberalbum_image" accept="image/*" required>
      </div>

      <div class="form-group">
        <label>&#x1f5dd;</label>
        <input type="text" class="form-control" name="memberalbum_token" required>
      </div>

      <div class="memberalbum-submit">
        <button type="submit" name="memberalbum_upload" class="btn btn-success">投稿</button>
      </div>
    </form>
  </div>
</details>
';
}




    /* ---------------------------------------------------------
     * Utility
     * --------------------------------------------------------- */
    private function loadTokens()
    {
        return json_decode(file_get_contents($this->tokenFile), true) ?: [];
    }

    private function loadMeta()
    {
        return json_decode(file_get_contents($this->metaFile), true) ?: [];
    }

    private function uuid()
    {
        return bin2hex(random_bytes(16));
    }

    private function redirectBack($msg = '')
    {
        $url = DOMAIN_BASE . 'album';

        if ($msg !== '') {
            // 必要ならメッセージをクエリに付ける
            $url .= '?msg=' . urlencode($msg);
        }

        header('Location: ' . $url);
    exit;
    }
}
