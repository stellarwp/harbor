/**
 * License key input.
 *
 * Renders in three states controlled by the parent:
 *   - Empty    (currentKey === null): editable input with Activate button.
 *   - Locked   (currentKey set, !isEditing): read-only display with inline Edit button.
 *   - Editing  (currentKey set, isEditing): editable input pre-filled with the stored key,
 *              plus Activate, Cancel, and Remove buttons.
 *
 * Wires activation to the @wordpress/data store.
 *
 * @package LiquidWeb\Harbor
 */
import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { KeyRound, Loader2, Pencil, Trash2 } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { store as harborStore } from '@/store';
import { useToast } from '@/context/toast-context';
import { HarborError } from '@/errors';

interface LicenseKeyInputProps {
	/** The currently stored key, or null when no license is active. */
	currentKey:  string | null;
	/** Whether the input is in edit mode. Controlled by the parent. */
	isEditing:   boolean;
	/** Called when the user clicks Edit to unlock the field. */
	onEdit:      () => void;
	/** Called when the user clicks Cancel to revert to locked state. */
	onCancel:    () => void;
	/** Called when the user confirms Remove. Returns null on success, HarborError on failure. */
	onRemove:    () => Promise<HarborError | null>;
	/** Called on successful activation so the parent can respond (e.g. exit edit mode). */
	onSuccess?:  () => void;
	/** When set, fills the input with this value (e.g. from a test-key cheat-sheet). */
	prefillKey?: string;
}

/**
 * @since 1.0.0
 */
export function LicenseKeyInput( {
	currentKey,
	isEditing,
	onEdit,
	onCancel,
	onRemove,
	onSuccess,
	prefillKey,
}: LicenseKeyInputProps ) {
	const [ key, setKey ]               = useState( '' );
	const [ localError, setLocalError ] = useState< string | null >( null );

	const { storeLicense } = useDispatch( harborStore );
	const { addToast }     = useToast();

	const { isStoring, canModifyLicense } = useSelect(
		( select ) => ( {
			isStoring:        select( harborStore ).isLicenseStoring(),
			canModifyLicense: select( harborStore ).canModifyLicense(),
		} ),
		[]
	);

	// Seed the editable value from the stored key when entering edit mode.
	useEffect( () => {
		if ( isEditing && currentKey ) {
			setKey( currentKey );
		}
		if ( ! isEditing ) {
			setKey( '' );
			setLocalError( null );
		}
	}, [ isEditing, currentKey ] );

	useEffect( () => {
		if ( prefillKey ) {
			setKey( prefillKey );
			setLocalError( null );
		}
	}, [ prefillKey ] );

	const handleActivate = async () => {
		const trimmedKey = key.trim();
		if ( ! trimmedKey ) {
			setLocalError( __( 'Please enter a license key.', '%TEXTDOMAIN%' ) );
			return;
		}
		setLocalError( null );
		const result = await storeLicense( trimmedKey );
		if ( result instanceof HarborError ) {
			addToast( result.message, 'error' );
		} else {
			addToast( __( 'License activated successfully.', '%TEXTDOMAIN%' ), 'success' );
			setKey( '' );
			onSuccess?.();
		}
	};

	const handleRemove = async () => {
		const error = await onRemove();
		if ( ! error ) {
			setKey( '' );
			setLocalError( null );
		}
	};

	const inputWithActivate = (
		<div className="flex gap-2">
			<div className="relative flex-1">
				<KeyRound className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
				<Input
					id="license-key-input"
					placeholder={ __( 'e.g. LWSW-UNIFIED-PRO-2025', '%TEXTDOMAIN%' ) }
					value={ key }
					onChange={ ( e ) => {
						setKey( e.target.value.toUpperCase() );
						if ( localError ) setLocalError( null );
					} }
					onKeyDown={ ( e ) => e.key === 'Enter' && canModifyLicense && handleActivate() }
					className="pl-10 font-mono uppercase"
					aria-invalid={ !! localError }
					aria-describedby={ localError ? 'license-key-error' : undefined }
					disabled={ ! canModifyLicense }
					// eslint-disable-next-line jsx-a11y/no-autofocus
					autoFocus={ isEditing }
				/>
			</div>
			<Button
				onClick={ handleActivate }
				disabled={ ! canModifyLicense || ! key.trim() }
			>
				{ isStoring ? (
					<>
						<Loader2 className="w-4 h-4 animate-spin" />
						{ __( 'Verifying\u2026', '%TEXTDOMAIN%' ) }
					</>
				) : (
					__( 'Activate', '%TEXTDOMAIN%' )
				) }
			</Button>
		</div>
	);

	// ----- Locked state -----
	if ( currentKey !== null && ! isEditing ) {
		return (
			<div className="flex items-center gap-2">
				<div className="relative flex-1">
					<KeyRound className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
					<Input
						readOnly
						value={ currentKey }
						className="pl-10 font-mono bg-muted/40 cursor-default select-all"
						tabIndex={ -1 }
					/>
				</div>
				<button
					type="button"
					onClick={ onEdit }
					className="flex shrink-0 items-center gap-1 text-[11px] text-muted-foreground transition-colors hover:opacity-75"
				>
					<Pencil className="w-3 h-3" />
					{ __( 'Edit', '%TEXTDOMAIN%' ) }
				</button>
			</div>
		);
	}

	// ----- Editing state -----
	if ( currentKey !== null && isEditing ) {
		return (
			<div className="space-y-2">
				{ inputWithActivate }
				<div className="flex items-center justify-between">
					<button
						type="button"
						onClick={ onCancel }
						disabled={ ! canModifyLicense }
						className="text-[11px] text-muted-foreground transition-colors hover:opacity-75 disabled:opacity-50"
					>
						{ __( 'Cancel', '%TEXTDOMAIN%' ) }
					</button>
					<button
						type="button"
						onClick={ handleRemove }
						disabled={ ! canModifyLicense }
						className="flex items-center gap-1 text-[11px] text-destructive transition-colors hover:opacity-75 disabled:opacity-50"
					>
						<Trash2 className="w-3 h-3" />
						{ __( 'Remove license', '%TEXTDOMAIN%' ) }
					</button>
				</div>
				{ localError && (
					<p id="license-key-error" className="text-sm text-destructive" role="alert">
						{ localError }
					</p>
				) }
			</div>
		);
	}

	// ----- Empty state -----
	return (
		<div className="space-y-3">
			<label className="text-sm font-medium" htmlFor="license-key-input">
				{ __( 'Enter License Key', '%TEXTDOMAIN%' ) }
			</label>
			{ inputWithActivate }
			{ isStoring && (
				<p className="text-sm text-muted-foreground flex items-center gap-1.5">
					<Loader2 className="w-3.5 h-3.5 animate-spin" />
					{ __( 'Checking license with server\u2026', '%TEXTDOMAIN%' ) }
				</p>
			) }
			{ localError && (
				<p id="license-key-error" className="text-sm text-destructive" role="alert">
					{ localError }
				</p>
			) }
		</div>
	);
}
