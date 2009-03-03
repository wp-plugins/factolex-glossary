=== Factolex Glossary ===
Contributors: akirk
Tags: glossary, post, factolex
Requires at least: 2.5
Tested up to: 2.7.1
Stable tag: 0.2

Allows you to add glossaries to your blog posts using definitions from Factolex.com

== Description ==

[Factolex.com](http://www.factolex.com/) is a fact lexicon. We split up knowledge into small sentences: facts

You can use the data from Factolex to describe terms you are using in your blog posts or blog pages, and then place the glossary anywhere in your post using the shortcode `[factolex]`.

Selecting terms for a post is easy: just click a button in the sidebar box and the plugin suggests terms to use. You can customize the definitions by creating an account at Factolex.com.

*Using the Factolex Glossary plugin does not require an account at Factolex.com*

All the selected terms and explanations are stored in your WordPress database, so after selecting the terms from Factolex you are not dependent on it.

Further Resources:
1. [Changelog](http://www.factolex.com/support/wordpress/#changelog)
2. [Screencast](http://www.factolex.com/support/wordpress/#screencast)
3. [Larger Screenshots](http://www.factolex.com/support/wordpress/#screenshots)

== Installation ==

1. Upload the whole `factolex-glossary/` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Make sure to activate the sidebar box called `Factolex Glossar` in your Add a Post view
4. After you have written a new blog post, use the `Check for terms` button to get a list of suggested terms. Click the ones you want to include.
5. Put `[factolex]` in your blog post where you want the glossary to show up

== Frequently Asked Questions ==

= There seems to be a bad explanation for a term that I want to have in my glossary. How can I change that? =

You can do this by creating your own lexicon at [Factolex.com](http://www.factolex.com/) by clicking the checkboxes for facts that you find relevant. After you specify your username in the *Factolex Glossary Settings* the definitions are updated accordingly.

= Will it degrade the performance of my blog? =

This should not be a problem. All the data is cached in your local WordPress database.

Just when you browse your own blog (still logged on to your blog account), we refresh your own lexicon (if you have specified a username). This might cause some slowdowns, but those don't happen for your users.

= How can I customize the look and feel? =

You can customize color, width and height through the Factolex Glossary Settings. If that's not enough, you can also edit the file `/wp-content/plugins/factolex-glossary/factolex-glossary.css`. Be careful when updating the plugin. You could consider copying your changes into your theme CSS file.

== Screenshots ==

1. This shows the box that will appear in your sidebar when editing a post. Click the "Check for terms" button to fetch terms that appear in your post.
2. This is how your post is being displayed after it has been analyzed by Factolex. Click on the terms to add them to your glossary.
3. The Factolex Glossary that appears on your post will look like this.
4. Settings screen
