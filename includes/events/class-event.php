<?php
/**
 * Public event value object.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable view of an event record.
 */
final class Event {
	public const ACCESS_MEMBERS_ONLY = 'members_only';
	public const ACCESS_OPEN = 'open';
	public const ACCESS_MEMBER_PRIORITY = 'member_priority';
	public const STATUS_DRAFT = 'draft';
	public const STATUS_PUBLISHED = 'published';
	public const STATUS_CANCELLED = 'cancelled';
	public const STATUS_COMPLETED = 'completed';

	/** @var array<string,mixed> */
	private array $data;

	/** @param array<string,mixed> $data Event data. */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/** @return array<string,mixed> */
	public function data(): array {
		return $this->data;
	}

	public function id(): int { return absint( $this->data['id'] ?? 0 ); }
	public function slug(): string { return sanitize_title( (string) ( $this->data['slug'] ?? '' ) ); }
	public function title(): string { return sanitize_text_field( (string) ( $this->data['title'] ?? '' ) ); }
	public function short_description(): string { return sanitize_textarea_field( (string) ( $this->data['short_description'] ?? '' ) ); }
	public function full_description(): string { return (string) ( $this->data['full_description'] ?? '' ); }
	public function event_date(): string { return sanitize_text_field( (string) ( $this->data['event_date'] ?? '' ) ); }
	public function start_time(): string { return sanitize_text_field( (string) ( $this->data['start_time'] ?? '' ) ); }
	public function end_time(): string { return sanitize_text_field( (string) ( $this->data['end_time'] ?? '' ) ); }
	public function location(): string { return sanitize_text_field( (string) ( $this->data['location'] ?? '' ) ); }
	public function location_id(): int { return absint( $this->data['location_id'] ?? 0 ); }
	public function category_ids(): array { return array_values( array_filter( array_map( 'absint', (array) ( $this->data['category_ids'] ?? array() ) ) ) ); }
	public function map_link(): string { return esc_url_raw( (string) ( $this->data['map_link'] ?? '' ) ); }
	public function cover_image(): string { return esc_url_raw( (string) ( $this->data['cover_image'] ?? '' ) ); }
	public function cover_id(): int { return absint( $this->data['cover_id'] ?? 0 ); }
	public function external_registration_url(): string { return esc_url_raw( (string) ( $this->data['external_registration_url'] ?? '' ) ); }
	public function external_provider_name(): string {
		$name = sanitize_text_field( (string) ( $this->data['external_provider_name'] ?? '' ) );
		return $name ?: 'Jogar Airsoft';
	}
	public function is_paid(): bool { return ! empty( $this->data['is_paid'] ); }
	public function price(): string { return sanitize_text_field( (string) ( $this->data['price'] ?? '' ) ); }
	public function player_limit(): int {
		return max( 0, absint( $this->data['player_limit'] ?? $this->data['max_players'] ?? 0 ) );
	}
	public function max_players(): int { return $this->player_limit(); }
	public function notes(): string { return sanitize_textarea_field( (string) ( $this->data['notes'] ?? '' ) ); }
	public function image_video_notice_disabled(): bool { return ! empty( $this->data['image_video_notice_disabled'] ); }
	public function checkin_token(): string { return sanitize_text_field( (string) ( $this->data['checkin_token'] ?? '' ) ); }
	public function checkin_enabled(): bool { return ! empty( $this->data['checkin_enabled'] ); }
	public function checkin_open_at(): string { return sanitize_text_field( (string) ( $this->data['checkin_open_at'] ?? '' ) ); }
	public function checkin_close_at(): string { return sanitize_text_field( (string) ( $this->data['checkin_close_at'] ?? '' ) ); }
	public function checkin_points(): int { return max( 0, absint( $this->data['checkin_points'] ?? 1 ) ); }
	public function checkin_bonus_enabled(): bool { return ! empty( $this->data['checkin_bonus_enabled'] ); }
	public function checkin_bonus_trigger_position(): int { return max( 0, absint( $this->data['checkin_bonus_trigger_position'] ?? 0 ) ); }
	public function checkin_bonus_points(): int { return max( 0, absint( $this->data['checkin_bonus_points'] ?? 0 ) ); }
	public function checkin_bonus_template(): string { return sanitize_key( (string) ( $this->data['checkin_bonus_template'] ?? 'bonus_unlocked' ) ); }
	public function checkin_bonus_custom_message(): string { return sanitize_textarea_field( (string) ( $this->data['checkin_bonus_custom_message'] ?? '' ) ); }
	public function checkin_bonus_count_manual(): bool { return ! empty( $this->data['checkin_bonus_count_manual'] ); }
	public function access_mode(): string {
		$mode = sanitize_key( (string) ( $this->data['access_mode'] ?? self::ACCESS_OPEN ) );
		return in_array( $mode, self::access_modes(), true ) ? $mode : self::ACCESS_OPEN;
	}
	public function waiting_list_enabled(): bool { return ! empty( $this->data['waiting_list_enabled'] ); }
	public function waiting_list_limit(): int { return max( 0, absint( $this->data['waiting_list_limit'] ?? 0 ) ); }
	public function registration_deadline(): string { return sanitize_text_field( (string) ( $this->data['registration_deadline'] ?? '' ) ); }
	public function priority_deadline(): string { return sanitize_text_field( (string) ( $this->data['priority_deadline'] ?? '' ) ); }
	public function status(): string {
		$status = sanitize_key( (string) ( $this->data['status'] ?? self::STATUS_DRAFT ) );
		return in_array( $status, self::statuses(), true ) ? $status : self::STATUS_DRAFT;
	}
	public function created_at(): string { return sanitize_text_field( (string) ( $this->data['created_at'] ?? '' ) ); }
	public function updated_at(): string { return sanitize_text_field( (string) ( $this->data['updated_at'] ?? '' ) ); }
	public function is_visible(): bool { return in_array( $this->status(), array( self::STATUS_PUBLISHED, self::STATUS_CANCELLED, self::STATUS_COMPLETED ), true ); }
	public function starts_at_timestamp(): int { return $this->timestamp( trim( $this->event_date() . ' ' . $this->start_time() ) ); }
	public function ends_at_timestamp(): int { return $this->timestamp( trim( $this->event_date() . ' ' . $this->end_time() ) ); }
	public function registration_deadline_timestamp(): int { return $this->timestamp( $this->registration_deadline() ); }
	public function priority_deadline_timestamp(): int { return $this->timestamp( $this->priority_deadline() ); }
	public function checkin_open_timestamp(): int { return $this->timestamp( $this->checkin_open_at() ); }
	public function checkin_close_timestamp(): int { return $this->timestamp( $this->checkin_close_at() ); }
	public function priority_window_open(): bool {
		return self::ACCESS_MEMBER_PRIORITY === $this->access_mode()
			&& 0 !== $this->priority_deadline_timestamp()
			&& $this->priority_deadline_timestamp() >= current_time( 'timestamp' );
	}
	public function is_registration_open(): bool {
		$deadline = $this->registration_deadline_timestamp();
		return self::STATUS_PUBLISHED === $this->status() && ( 0 === $deadline || $deadline >= current_time( 'timestamp' ) );
	}
	public function is_checkin_window_open(): bool {
		if ( ! $this->checkin_enabled() || in_array( $this->status(), array( self::STATUS_DRAFT, self::STATUS_CANCELLED ), true ) ) {
			return false;
		}
		$now = current_time( 'timestamp' );
		return ( ! $this->checkin_open_timestamp() || $now >= $this->checkin_open_timestamp() )
			&& ( ! $this->checkin_close_timestamp() || $now <= $this->checkin_close_timestamp() );
	}
	public static function statuses(): array { return array( self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_CANCELLED, self::STATUS_COMPLETED ); }
	public static function access_modes(): array { return array( self::ACCESS_MEMBERS_ONLY, self::ACCESS_OPEN, self::ACCESS_MEMBER_PRIORITY ); }
	private function timestamp( string $value ): int {
		$value = trim( $value );
		$timestamp = $value ? strtotime( $value ) : false;
		return false === $timestamp ? 0 : $timestamp;
	}
}
