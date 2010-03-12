<?php
/*
Plugin Name: BIC Archives
Plugin URI: http://www.bestinclass.com/blog/projects/bic-archives
Description: Simple, efficient, and flexible archive support via shortcodes.
Version: 1.0
Author: Bill Lipa
Author URI: http://masterleep.com/
*/


class BICArchives
{
  
  const ONE_DAY = 86400;
  const KEYS = "bic_archives_keys";
  

  function __construct()
  {
    add_shortcode('bic_archives', array(&$this, 'bic_archives'));
    add_action('save_post', array(&$this, 'drop_cache'));
    add_action('edit_post', array(&$this, 'drop_cache'));
    add_action('delete_post', array(&$this, 'drop_cache'));
  }
  
  static function instance()
  {
    static $instance = null;
    if ($instance == null)
      $instance = new BICArchives();
    return $instance;
  }
  
  function bic_archives($atts)
  {
    extract(shortcode_atts(array(
      'cat_ids' => null,
      'limit' => -1,
      ), $atts));

    $cache_id = $this->cache_id($cat_ids, $limit);
    $cached = get_transient($cache_id);
    if ($cached)
    {
      $this->log("returning cached " . $cache_id);
      return $cached;
    }

    $this->log("generating " . $cache_id);
    $posts = $this->get_posts($cat_ids);
    $html = $this->generate_html($posts);
    $this->store_cache($cache_id, $html);
    return $html;
  }
  
  function generate_html($posts)
  {
    $html = "<ul class='bic_archives'>\n";
    $cur_month = null;
    $post_html = "";
    $num_posts = 0;
    foreach ($posts as $post)
    {
      $month = mysql2date('F Y', $post->post_date);
      if ($month != $cur_month)
      {
        if ($cur_month)
          $html .= $this->generate_posts($cur_month, $post_html, $num_posts);
        $cur_month = $month;
        $post_html = "";
        $num_posts = 0;
      }
      $post_html .= "<li><em>" . mysql2date('d', $post->post_date) . ":</em> ";
      $post_html .= "<a href='" . get_permalink($post->ID) . "'>" . get_the_title($post->ID) . "</a>";
      $post_html .= "&nbsp;<span>(" . $post->comment_count . ")</span></li>\n";
      $num_posts += 1;
    }
    $html .= $this->generate_posts($month, $post_html, $num_posts);
    $html .= "</ul>\n";
    return $html;
  }
  
  function generate_posts($month, $post_html, $num_posts)
  {
    if ($num_posts == 0)
      return "";
    $res = "<li><h3>" . $month . " <span>(" . $num_posts . ")</span></h3>\n";
    $res .= "<ul class='month'>";
    $res .= $post_html;
    $res .= "</ul></li>";
    return $res;
  }
  
  function get_posts($cat_ids)
  {
    global $wpdb;
    
    $query = "SELECT DISTINCT ID, post_date, post_title, comment_count FROM " . $wpdb->posts;
    if ($cat_ids)
    {
      $cat_clause = $this->category_sql($cat_ids);
      $query .= " INNER JOIN " . $wpdb->term_relationships .
        " ON (" . $wpdb->posts . ".ID = " . $wpdb->term_relationships . ".object_id)";
      $query .= " INNER JOIN " . $wpdb->term_taxonomy .
        " ON (" . $wpdb->term_relationships . ".term_taxonomy_id = " . $wpdb->term_taxonomy . ".term_taxonomy_id)";
      $post_where = " AND " . $wpdb->term_taxonomy . ".taxonomy = 'category' AND " .
        $wpdb->term_taxonomy . ".term_id " . $cat_clause;
    }
    $query .= " WHERE post_date AND post_status='publish' AND post_type='post' AND post_password=''" . $post_where;
    $query .= " ORDER BY post_date DESC";
    $this->log($query);
    return $wpdb->get_results($query);
  }
  
  function category_sql($cat_ids)
  {
    return ($cat_ids[0] == "-" ? "NOT " : "") . "IN (" . str_replace("-", "", $cat_ids) . ")";
  }
  
  // Return a unique identifier that maps like archives to the same cache key.
  function cache_id($cat_ids, $limit)
  {
    return "bic_archives_cat_" . $cat_ids . "_lim_" . $limit;
  }
  
  // Save the generated HTML in the transient store.
  function store_cache($cache_id, $html)
  {
    set_transient($cache_id, $html, self::ONE_DAY);
    $keys = get_transient(self::KEYS);
    if (!$keys)
      $keys = array();
    $keys[$cache_id] = true;
    set_transient(self::KEYS, $keys, self::ONE_DAY);
    $this->log("saving keys as " . $keys);
  }
  
  // Delete an individual cached archive.
  function drop_item($item, $key)
  {
    $this->log("deleting " . $key);
    delete_transient($key);
  }
  
  // Delete all the cached archives.
  function drop_cache()
  {
    $this->log("deleting bic_archives");
    $keys = get_transient(self::KEYS);
    if (!$keys)
      return;
    array_walk($keys, array($this, 'drop_item'));
    delete_transient(self::KEYS);
  }
  
  // Debug log
  function log($msg)
  {
    error_log($msg, 0);
  }

}


BICArchives::instance();

?>
