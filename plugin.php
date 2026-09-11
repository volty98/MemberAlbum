<?php

class pluginMemberAlbum extends Plugin
{
    private $uploadDir;
    private $metaFile;
    private $tokenFile;

    public function init()
    {
        // プラグインフォルダ
        $this->tokenFile = $this->phpPath() . 'token.json';

        // 画像保存先
        $this->uploadDir = PATH_CONTENT . 'uploads/MemberAlbum/';
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

        $html  = '<h3>MemberAlbum トークン管理</h3>';
        $html .= '<p>ユーザー名ごとに投稿トークンを発行します。</p>';

        $html .= '<label>ユーザー名</label><br>';
        $html .= '<input type="text" name="memberalbum_new_user" placeholder="ユーザー名">';
        $html .= '<button type="submit" name="memberalbum_generate_token">トークン発行</button>';

        if (!empty($tokens)) {
            $html .= '<hr><h4>既存トークン一覧</h4><ul>';
            foreach ($tokens as $user => $token) {
                $html .= '<li>' . $user . ' : ' . $token . '</li>';
            }
            $html .= '</ul>';
        }

        // デバッグ情報（安全版）
        $html .= '<hr><h4>Plugin Files Information</h4>';
        $html .= '<p>uploadDir: ' . $this->uploadDir . '</p>';
        $html .= '<p>metaFile: ' . $this->metaFile . '</p>';
        $html .= '<p>tokenFile: ' . $this->tokenFile . '</p>';

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
            exit('トークンが無効です');
        }

        if (!isset($_FILES['memberalbum_image'])) {
            exit('ファイルがありません');
        }

        $tmp  = $_FILES['memberalbum_image']['tmp_name'];
        $mime = mime_content_type($tmp);

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed)) {
            exit('画像形式が不正です');
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

        exit('投稿完了');
    }

    /* ---------------------------------------------------------
     * Album ページに HTML を追加
     * --------------------------------------------------------- */
    public function pageEnd()
    {
        global $page;

        if ($page->slug() !== 'album') return;

        echo $this->gallery();
        echo $this->uploadForm();
    }

    private function gallery()
    {
        $meta  = $this->loadMeta();
        $files = glob($this->uploadDir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE);

        $html  = '<div class="memberalbum-gallery">';
        $html .= '<h3>Member Album</h3>';

        foreach ($files as $file) {
            $name = basename($file);
            $user = $meta[$name]['user'] ?? 'anonymous';
            $src  = DOMAIN_BASE . '/bl-content/uploads/MemberAlbum/' . $name;

            $html .= '<div class="memberalbum-item">';
            $html .= '<img src="' . $src . '">';
            $html .= '<p>投稿者: ' . $user . '</p>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function uploadForm()
    {
        return '
        <div class="memberalbum-upload">
            <h4>画像投稿</h4>
            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="memberalbum_token" placeholder="投稿トークン" required><br>
                <input type="file" name="memberalbum_image" accept="image/*" required><br>
                <button type="submit" name="memberalbum_upload">投稿する</button>
            </form>
        </div>';
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
}
