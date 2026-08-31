<?php
/**
 * Plugin bootstrap.
 *
 * @package TBT_Drag_Drop
 */

namespace TBT\DragDrop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static ?Plugin $instance = null;
	private bool $booted = false;
	private Exercise_Repository $repository;
	private Renderer $renderer;

	/**
	 * Return the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot plugin services.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$validator        = new Exercise_Validator();
		$this->repository = new Exercise_Repository( $validator );
		$assets           = new Assets();
		$this->renderer   = new Renderer( $this->repository, $assets );

		( new Post_Type() )->hooks();
		$assets->hooks();
		( new Shortcode( $this->renderer ) )->hooks();
		( new Tools_Shortcode( $this->repository, $assets ) )->hooks();
		( new Template_Loader() )->hooks();
		( new Exercises_Controller( $this->repository ) )->hooks();
		( new Admin( $this->repository, $validator ) )->hooks();

		// Activation does not fire for an already-active plugin, so an existing
		// install picks the capability up here instead.
		add_action( 'init', array( Access::class, 'maybe_add_caps' ), 5 );

		// After Post_Type::register(), which runs on init at the default
		// priority, so the rules being written include this post type.
		add_action( 'init', array( Activator::class, 'maybe_flush_rewrites' ), 20 );
	}

	/**
	 * Get renderer service.
	 *
	 * @return Renderer
	 */
	public function renderer(): Renderer {
		return $this->renderer;
	}

	/**
	 * Get repository service.
	 *
	 * @return Exercise_Repository
	 */
	public function repository(): Exercise_Repository {
		return $this->repository;
	}
}
