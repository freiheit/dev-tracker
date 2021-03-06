<?php
class Steam extends Kurl {
    private static $profileBase = 'http://steamcommunity.com/profiles/{{userIdentifier}}/posthistory/';
    private static $idBase = 'http://steamcommunity.com/id/{{userIdentifier}}/posthistory/';

    private $posts = array();

    public function __construct( $uid, $identifier ){
        $this->uid = $uid;
        $this->identifier = $identifier;

        include_once( __DIR__ . '/../simple_html_dom.php' );
    }

    public function getRecentPosts(){
        if( is_numeric( $this->identifier ) ) :
            $url = str_replace( '{{userIdentifier}}', $this->identifier, self::$profileBase );
        else :
            $url = str_replace( '{{userIdentifier}}', $this->identifier, self::$idBase );
        endif;

        $html = str_get_html( $this->loadUrl( $url ) );

        // Find all article blocks
        foreach( $html->find( 'div.post_searchresult' ) as $communityPost ) :
            // Parse time into a timestamp
            $time = $communityPost->find( 'div.searchresult_timestamp', 0 )->plaintext;
            if( preg_match( '#[1-2][0-9]{3}\s@#mis', $time ) ) :
                $time = str_replace( array( '@ ', ',' ), '', $time );
            else:
                $time = str_replace( '@', date( 'o' ), $time );
            endif;

            // Parse post url to a valid one
            $url = $communityPost->find( 'div.post_searchresult_simplereply', 0 )->onclick;
            $url = str_replace( 'window.location=', '', $url );
            $url = str_replace( '\'', '', $url );

            $post = new Post();

            preg_match( '#http://steamcommunity.com/app/(\d*)/discussions/\d+/#mis', $communityPost->find( 'a.searchresult_forum_link', 0 )->href, $matches );

            if( !isset( $matches[ 1 ] ) ):
                preg_match( '#http://steamcommunity.com/workshop/discussions/\?appid=(\d*)#mis', $communityPost->find( 'a.searchresult_forum_link', 0 )->href, $matches );
            endif;

            if( isset( $matches[ 1 ] ) ):
                $post->setSection( $matches[ 1 ] );
            else :
                $post->setSection( false );
            endif;

            $post->setTimestamp( strtotime( $time ) );
            $post->setTopic( $communityPost->find( 'a.forum_topic_link', 0 )->plaintext, $communityPost->find( 'a.forum_topic_link', 0 )->href );
            $post->setText( $communityPost->find( 'div.post_searchresult_simplereply', 0 )->innertext );
            $post->setUrl( $url );
            $post->setUserId( $this->uid );

            $post->setSource( 'Steam' );

            if( strpos( $post->text, 'blockquote' ) !== false ):
                $post->text = preg_replace( '/href="\#(.+?)"/mis', 'href="' . $communityPost->find( 'a.forum_topic_link', 0 )->href . '#$1"', $post->text );
            endif;

            $this->posts[] = $post;
        endforeach;

        return $this->posts;
    }
}
