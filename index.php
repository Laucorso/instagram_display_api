<?php
    require_once __DIR__ . '/vendor/autoload.php'; // change path as needed

    require_once('instagramApi.php');

    $params = array(
        'get_code'=>isset( $_GET['code']) ? $_GET['code'] : '',
    );
    
    $ig = new instagramApi($params)

?>

<?php if($ig->hasUserAccessToken) : ?>

    <?php $usersMedia = $ig->getUsersMedia(); ?>
    <h4>JSON DATA</h4>
    <?php print_r( $usersMedia ); ?>

<?php else : ?>

    <a href="<?php echo $ig->authorizationUrl; ?>">
        Authorize Insta
    </a>

<?php endif; ?>
