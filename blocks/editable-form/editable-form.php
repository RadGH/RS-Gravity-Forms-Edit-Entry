<?php

/**
 * @global   array $block The block settings and attributes.
 * @global   string $content The block inner HTML (empty).
 * @global   bool $is_preview True during backend preview render.
 * @global   int $post_id The post ID the block is rendering content against.
 *           This is either the post ID currently being displayed inside a query loop,
 *           or the post ID of the post hosting this block.
 * @global   array $context The context provided to the block by the post or it's parent block.
 */

$form_id = get_field( 'form_id', $block['id'] );
$title = (bool) get_field( 'title', $block['id'] );
$description = (bool) get_field( 'description', $block['id'] );
$confirmation_message = get_field( 'confirmation_message', $block['id'] );

if ( ! $form_id ) {
	if ( $is_preview ) {
		echo '<p>Select a form</p>';
	}
	return;
}

echo RS_Gravity_Forms_Edit_Entry_Form::get_instance()->prepare_editable_form( $form_id, $title, $description, $confirmation_message );

// Show the form on the block editor
if ( $is_preview ) {
	?>
	<style>
		.gform_wrapper { display: block !important; }
	</style>
	<?php
}

/*
$allowed_blocks = array(
	'gravityforms/form',
);

// [0] = Block name
// [1] = Block settings
// [2] = Array of child blocks (same format)
$my_block_template = array(
    array(
        'gravityforms/form',
        array(
			'allowEditingEntries' => true,
		),
        array(),
    ),
);
?>
<InnerBlocks
	allowedBlocks="<?php echo esc_attr( wp_json_encode( $allowed_blocks ) ); ?>"
	template="<?php echo esc_attr( wp_json_encode( $my_block_template ) ); ?>"
	templateLock="all" />
<?php
*/
