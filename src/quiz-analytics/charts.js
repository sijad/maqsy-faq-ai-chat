import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export const formatNumber = (n) => new Intl.NumberFormat().format(n);
export const formatPercent = (ratio) =>
	new Intl.NumberFormat(undefined, {
		style: 'percent',
		maximumFractionDigits: 1,
	}).format(ratio || 0);
const compact = (n) =>
	new Intl.NumberFormat(undefined, { notation: 'compact' }).format(n);

/**
 * Clean axis ticks: 0 and a few round steps up to (at least) max.
 *
 * @param {number} max   The largest value to cover.
 * @param {number} count Roughly how many steps to use.
 * @return {number[]} Tick values.
 */
function niceTicks(max, count = 4) {
	if (max <= 0) {
		return [0, 1];
	}
	const raw = max / count;
	const magnitude = 10 ** Math.floor(Math.log10(raw));
	const step = [1, 2, 5, 10].map((m) => m * magnitude).find((s) => s >= raw);
	const ticks = [];
	for (let t = 0; t <= max + step * 0.001; t += step) {
		ticks.push(t);
	}
	if (ticks[ticks.length - 1] < max) {
		ticks.push(ticks[ticks.length - 1] + step);
	}
	return ticks;
}

function useWidth() {
	const ref = useRef(null);
	const [width, setWidth] = useState(600);
	useEffect(() => {
		if (!ref.current) {
			return;
		}
		const observer = new window.ResizeObserver(([entry]) =>
			setWidth(Math.max(280, entry.contentRect.width))
		);
		observer.observe(ref.current);
		return () => observer.disconnect();
	}, []);
	return [ref, width];
}

const formatDay = (iso, options) =>
	new Date(iso + 'T00:00:00Z').toLocaleDateString(undefined, {
		timeZone: 'UTC',
		...options,
	});

/**
 * Multi-series daily line chart with a crosshair tooltip.
 *
 * @param {Object}   props
 * @param {Object[]} props.data   Rows with a `date` key plus one numeric key per series.
 * @param {Object[]} props.series [{ key, label, color }].
 * @param {string}   props.label  Accessible chart description.
 */
export function LineChart({ data, series, label }) {
	const [ref, width] = useWidth();
	const [hover, setHover] = useState(null);
	const height = 220;
	const margin = { top: 12, right: 16, bottom: 28, left: 40 };
	const innerW = width - margin.left - margin.right;
	const innerH = height - margin.top - margin.bottom;

	const max = Math.max(
		0,
		...data.flatMap((row) => series.map((s) => row[s.key]))
	);
	const ticks = niceTicks(max);
	const top = ticks[ticks.length - 1];
	const x = (i) =>
		margin.left +
		(data.length <= 1 ? innerW / 2 : (i / (data.length - 1)) * innerW);
	const y = (v) => margin.top + innerH - (v / top) * innerH;

	// Roughly one date label per 80px.
	const labelEvery = Math.max(
		1,
		Math.ceil(data.length / Math.max(1, Math.floor(innerW / 80)))
	);

	const onMove = (event) => {
		const rect = event.currentTarget.getBoundingClientRect();
		const px = ((event.clientX - rect.left) / rect.width) * width;
		const index =
			data.length <= 1
				? 0
				: Math.round(((px - margin.left) / innerW) * (data.length - 1));
		setHover(Math.max(0, Math.min(data.length - 1, index)));
	};

	const hovered = hover !== null ? data[hover] : null;

	return (
		<div className="maqsy-qa-chart" ref={ref}>
			<div className="maqsy-qa-legend" aria-hidden="true">
				{series.map((s) => (
					<span key={s.key}>
						<i style={{ background: s.color }} /> {s.label}
					</span>
				))}
			</div>
			<svg
				width={width}
				height={height}
				role="img"
				aria-label={label}
				onMouseMove={onMove}
				onMouseLeave={() => setHover(null)}
			>
				{ticks.map((t) => (
					<g key={t}>
						<line
							x1={margin.left}
							x2={width - margin.right}
							y1={y(t)}
							y2={y(t)}
							className="maqsy-qa-grid"
						/>
						<text
							x={margin.left - 8}
							y={y(t)}
							dy="0.32em"
							textAnchor="end"
							className="maqsy-qa-axis"
						>
							{compact(t)}
						</text>
					</g>
				))}
				{data.map((row, i) =>
					i % labelEvery === 0 ? (
						<text
							key={row.date}
							x={x(i)}
							y={height - 8}
							textAnchor="middle"
							className="maqsy-qa-axis"
						>
							{formatDay(row.date, {
								month: 'short',
								day: 'numeric',
							})}
						</text>
					) : null
				)}
				{series.map((s) => (
					<g key={s.key}>
						<path
							d={
								`M${x(0)},${y(0)} ` +
								data
									.map(
										(row, i) => `L${x(i)},${y(row[s.key])}`
									)
									.join(' ') +
								` L${x(data.length - 1)},${y(0)} Z`
							}
							fill={s.color}
							opacity="0.08"
						/>
						<polyline
							points={data
								.map((row, i) => `${x(i)},${y(row[s.key])}`)
								.join(' ')}
							fill="none"
							stroke={s.color}
							strokeWidth="2"
							strokeLinejoin="round"
							strokeLinecap="round"
						/>
					</g>
				))}
				{hovered && (
					<g>
						<line
							x1={x(hover)}
							x2={x(hover)}
							y1={margin.top}
							y2={margin.top + innerH}
							className="maqsy-qa-crosshair"
						/>
						{series.map((s) => (
							<circle
								key={s.key}
								cx={x(hover)}
								cy={y(hovered[s.key])}
								r="4.5"
								fill={s.color}
								stroke="#fff"
								strokeWidth="2"
							/>
						))}
					</g>
				)}
			</svg>
			{hovered && (
				<div
					className="maqsy-qa-tooltip"
					style={{
						left: Math.min(x(hover) + 12, width - 170),
						top: margin.top,
					}}
					role="status"
				>
					<strong>
						{formatDay(hovered.date, {
							weekday: 'short',
							month: 'short',
							day: 'numeric',
							year: 'numeric',
						})}
					</strong>
					{series.map((s) => (
						<span key={s.key}>
							<i style={{ background: s.color }} /> {s.label}:{' '}
							<b>{formatNumber(hovered[s.key])}</b>
						</span>
					))}
				</div>
			)}
		</div>
	);
}

/**
 * Horizontal bars with labels on the left and the value at each bar's tip.
 *
 * @param {Object}   props
 * @param {Object[]} props.items [{ key, label, value, note? }].
 * @param {number}   props.total Optional denominator for a percentage in the tooltip.
 * @param {string}   props.color Bar color.
 */
export function BarList({ items, total, color = '#2a78d6' }) {
	const max = Math.max(1, ...items.map((item) => item.value));
	if (!items.length) {
		return (
			<p className="description">
				{__('No data yet.', 'maqsy-faq-ai-chat')}
			</p>
		);
	}
	return (
		<ul className="maqsy-qa-bars">
			{items.map((item) => {
				const share = total
					? ` (${formatPercent(item.value / total)})`
					: '';
				return (
					<li
						key={item.key}
						title={`${item.label}: ${formatNumber(item.value)}${share}`}
					>
						<span className="maqsy-qa-bars-label">
							{item.label}
						</span>
						<span className="maqsy-qa-bars-track">
							<span
								className="maqsy-qa-bars-bar"
								style={{
									width: `calc((100% - 9em) * ${item.value / max})`,
									background: color,
								}}
							/>
							<span className="maqsy-qa-bars-value">
								{formatNumber(item.value)}
								{share && <small>{share}</small>}
								{item.note && <small> · {item.note}</small>}
							</span>
						</span>
					</li>
				);
			})}
		</ul>
	);
}
