import { ImagePlus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

const size = 160;
const outputSize = 384;

export function AdminImageInput({
    initial,
    label,
    error,
    onChange,
    onPendingChange,
}: {
    initial?: string | null;
    label: string;
    error?: string;
    onChange: (file: File) => void;
    onPendingChange: (pending: boolean) => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    const image = useRef<HTMLImageElement>(null);
    const drag = useRef<{
        x: number;
        y: number;
        left: number;
        top: number;
    } | null>(null);
    const [source, setSource] = useState('');
    const [preview, setPreview] = useState('');
    const [editing, setEditing] = useState(false);
    const [saving, setSaving] = useState(false);
    const [dimensions, setDimensions] = useState({ width: size, height: size });
    const [loaded, setLoaded] = useState(false);
    const [zoom, setZoom] = useState(1);
    const [offset, setOffset] = useState({ left: 0, top: 0 });
    const [message, setMessage] = useState('');

    useEffect(
        () => () => {
            if (source) URL.revokeObjectURL(source);
        },
        [source],
    );
    useEffect(
        () => () => {
            if (preview) URL.revokeObjectURL(preview);
        },
        [preview],
    );

    const scale = (size / Math.min(dimensions.width, dimensions.height)) * zoom;
    const width = dimensions.width * scale;
    const height = dimensions.height * scale;
    const limit = (value: number, extent: number) => {
        const max = (extent - size) / 2;
        return Math.max(-max, Math.min(max, value));
    };
    const left = limit(offset.left, width);
    const top = limit(offset.top, height);

    const closeCrop = () => {
        setSource('');
        setEditing(false);
        setSaving(false);
        onPendingChange(false);
    };

    const save = () => {
        if (!image.current || !loaded) return;

        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = outputSize;
        const context = canvas.getContext('2d');
        if (!context) {
            setMessage('Trình duyệt không thể cắt ảnh này.');
            return;
        }

        context.drawImage(
            image.current,
            (width / 2 - size / 2 - left) / scale,
            (height / 2 - size / 2 - top) / scale,
            size / scale,
            size / scale,
            0,
            0,
            outputSize,
            outputSize,
        );
        setSaving(true);
        canvas.toBlob((blob) => {
            if (!blob) {
                setSaving(false);
                setMessage('Không thể lưu vùng ảnh đã chọn.');
                return;
            }

            const file = new File([blob], 'avatar.png', { type: 'image/png' });
            setPreview(URL.createObjectURL(file));
            onChange(file);
            closeCrop();
        }, 'image/png');
    };

    const shown = preview || initial;

    return (
        <div className="grid justify-items-center gap-2 border-b pb-4">
            <input
                ref={input}
                hidden
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    event.target.value = '';
                    if (!file) return;

                    setMessage('');
                    setLoaded(false);
                    setZoom(1);
                    setOffset({ left: 0, top: 0 });
                    setSource(URL.createObjectURL(file));
                    setEditing(true);
                    onPendingChange(true);
                }}
            />
            {editing ? (
                <>
                    <div
                        className="relative touch-none overflow-hidden rounded-full bg-muted ring-1 ring-border"
                        style={{ width: size, height: size }}
                        tabIndex={0}
                        aria-label="Vùng cắt ảnh. Dùng phím mũi tên để di chuyển ảnh."
                        onKeyDown={(event) => {
                            const step = {
                                ArrowLeft: [-5, 0],
                                ArrowRight: [5, 0],
                                ArrowUp: [0, -5],
                                ArrowDown: [0, 5],
                            }[event.key];

                            if (!step) return;
                            event.preventDefault();
                            setOffset({
                                left: limit(left + step[0], width),
                                top: limit(top + step[1], height),
                            });
                        }}
                        onPointerDown={(event) => {
                            if (event.button !== 0) return;
                            event.currentTarget.setPointerCapture(
                                event.pointerId,
                            );
                            drag.current = {
                                x: event.clientX,
                                y: event.clientY,
                                left,
                                top,
                            };
                        }}
                        onPointerMove={(event) => {
                            const start = drag.current;
                            if (!start) return;
                            setOffset({
                                left: limit(
                                    start.left + event.clientX - start.x,
                                    width,
                                ),
                                top: limit(
                                    start.top + event.clientY - start.y,
                                    height,
                                ),
                            });
                        }}
                        onLostPointerCapture={() => {
                            drag.current = null;
                        }}
                    >
                        <img
                            ref={image}
                            src={source}
                            alt=""
                            draggable={false}
                            className="pointer-events-none absolute top-1/2 left-1/2 max-w-none"
                            style={{
                                width,
                                height,
                                transform: `translate(calc(-50% + ${left}px), calc(-50% + ${top}px))`,
                                visibility: loaded ? 'visible' : 'hidden',
                            }}
                            onLoad={(event) => {
                                setDimensions({
                                    width: event.currentTarget.naturalWidth,
                                    height: event.currentTarget.naturalHeight,
                                });
                                setLoaded(true);
                            }}
                            onError={() => {
                                closeCrop();
                                setMessage('Không mở được ảnh đã chọn.');
                            }}
                        />
                    </div>
                    <p className="text-center text-xs text-muted-foreground">
                        Kéo ảnh để chọn vùng trong vòng tròn.
                    </p>
                    <label className="flex items-center gap-2 text-sm">
                        Thu phóng
                        <input
                            type="range"
                            min={1}
                            max={3}
                            step={0.05}
                            value={zoom}
                            onChange={(event) =>
                                setZoom(event.target.valueAsNumber)
                            }
                        />
                    </label>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={closeCrop}
                            disabled={saving}
                        >
                            Huỷ cắt
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={save}
                            disabled={!loaded || saving}
                        >
                            Dùng ảnh này
                        </Button>
                    </div>
                </>
            ) : (
                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    className="grid size-24 place-items-center overflow-hidden rounded-full border border-dashed bg-muted text-muted-foreground hover:bg-accent"
                    aria-label={label}
                >
                    {shown ? (
                        <img
                            src={shown}
                            alt=""
                            className="size-full object-cover"
                        />
                    ) : (
                        <ImagePlus className="size-6" />
                    )}
                </button>
            )}
            {!editing && (
                <p className="text-sm text-muted-foreground">{label}</p>
            )}
            <InputError message={message || error} />
        </div>
    );
}
