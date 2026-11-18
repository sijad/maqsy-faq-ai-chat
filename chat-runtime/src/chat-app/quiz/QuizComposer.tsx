import { useEffect, useRef, useState } from "react";

import { Button } from "../ui/button";
import { Textarea } from "../ui/textarea";
import { sprintf } from "../lib/quiz";
import type { QuizStrings } from "../types";
import type { ActiveQuiz, QuizController } from "../useQuiz";

interface QuizComposerProps {
	quiz: QuizController;
	active: ActiveQuiz;
	strings: QuizStrings;
}

const ghostButton =
	"size-auto h-7 rounded-md bg-transparent px-2 text-xs font-medium text-gray-600 hover:bg-gray-200 hover:brightness-100 dark:text-gray-300 dark:hover:bg-gray-700";

/**
 * Replaces the chat input while a quiz is running: progress, the current
 * question's answer controls, and skip/pause/restart/end actions.
 */
export function QuizComposer({ quiz, active, strings }: QuizComposerProps) {
	const question = active.question!;
	const [selected, setSelected] = useState<string[]>([]);
	const [text, setText] = useState("");
	const firstControl = useRef<HTMLButtonElement & HTMLTextAreaElement>(null);

	// Fresh controls for every question, with focus moved to the first one so
	// keyboard and screen-reader users land on the new question's answers.
	useEffect(() => {
		setSelected([]);
		setText("");
		const id = window.setTimeout(() => firstControl.current?.focus(), 0);
		return () => window.clearTimeout(id);
	}, [question.id]);

	const step = Math.min(active.progress.answered + 1, Math.max(active.progress.total, 1));
	const total = Math.max(active.progress.total, step);
	const percent = Math.round((active.progress.answered / total) * 100);
	const disabled = quiz.busy;

	return (
		<div className="border-t border-gray-200 dark:border-gray-700">
			<div className="flex items-center gap-2 px-3 pt-2">
				<div className="min-w-0 flex-1">
					<div className="flex items-baseline justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
						<span className="truncate font-medium text-gray-700 dark:text-gray-200">{active.quiz.title}</span>
						<span className="shrink-0">{sprintf(strings.progress, step, total)}</span>
					</div>
					<div
						className="mt-1 h-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
						role="progressbar"
						aria-valuemin={0}
						aria-valuemax={100}
						aria-valuenow={percent}
						aria-label={sprintf(strings.progress, step, total)}
					>
						<div className="h-full rounded-full bg-[var(--maqsy-primary,#2563eb)] transition-[width] duration-300" style={{ width: `${percent}%` }} />
					</div>
				</div>
			</div>

			<form
				className="flex max-h-56 flex-col gap-2 overflow-y-auto p-3"
				onSubmit={(event) => {
					event.preventDefault();
					if (question.type === "multiple" && selected.length) {
						quiz.answer(selected);
					} else if (question.type === "text" && text.trim()) {
						quiz.answer(text.trim());
					}
				}}
			>
				{question.type === "single" && (
					<div className="flex flex-col gap-1.5" role="group" aria-label={question.text}>
						{question.options.map((option, index) => (
							<button
								key={option.id}
								ref={index === 0 ? firstControl : undefined}
								type="button"
								disabled={disabled}
								onClick={() => quiz.answer(option.id)}
								className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm text-gray-900 outline-none transition-colors hover:border-[var(--maqsy-primary,#2563eb)] hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-blue-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
							>
								<span dir="auto">{option.label}</span>
							</button>
						))}
					</div>
				)}

				{question.type === "multiple" && (
					<>
						<p className="text-xs text-gray-500 dark:text-gray-400">{strings.selectHint}</p>
						<div className="flex flex-wrap gap-1.5" role="group" aria-label={question.text}>
							{question.options.map((option, index) => {
								const isOn = selected.includes(option.id);
								return (
									<button
										key={option.id}
										ref={index === 0 ? firstControl : undefined}
										type="button"
										aria-pressed={isOn}
										disabled={disabled}
										onClick={() =>
											setSelected((current) =>
												isOn ? current.filter((id) => id !== option.id) : [...current, option.id],
											)
										}
										className={`rounded-full border px-3 py-1.5 text-sm outline-none transition-colors focus-visible:ring-2 focus-visible:ring-blue-500 disabled:opacity-50 ${
											isOn
												? "border-[var(--maqsy-primary,#2563eb)] bg-[var(--maqsy-primary,#2563eb)] text-white"
												: "border-gray-300 bg-white text-gray-900 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
										}`}
									>
										<span aria-hidden="true">{isOn ? "✓ " : ""}</span>
										<span dir="auto">{option.label}</span>
									</button>
								);
							})}
						</div>
						<Button type="submit" disabled={disabled || !selected.length} className="size-auto h-8 w-full">
							{strings.continue}
						</Button>
					</>
				)}

				{question.type === "text" && (
					<div className="relative flex">
						<label className="sr-only" htmlFor="maqsy-quiz-input">
							{question.text}
						</label>
						<Textarea
							id="maqsy-quiz-input"
							ref={firstControl}
							rows={question.input === "text" ? 2 : 1}
							inputMode={question.input === "email" ? "email" : question.input === "tel" ? "tel" : undefined}
							autoComplete={question.input === "email" ? "email" : question.input === "tel" ? "tel" : "off"}
							className="min-h-9 flex-1 resize-none py-1.5 pr-11"
							value={text}
							maxLength={1000}
							disabled={disabled}
							aria-invalid={quiz.error ? true : undefined}
							aria-describedby={quiz.error ? "maqsy-quiz-error" : undefined}
							placeholder={question.placeholder || strings.answerHint}
							onChange={(event) => setText(event.target.value)}
							onKeyDown={(event) => {
								if (event.key === "Enter" && !event.shiftKey) {
									event.preventDefault();
									event.currentTarget.form?.requestSubmit();
								}
							}}
						/>
						<Button type="submit" disabled={disabled || !text.trim()} aria-label={strings.continue} className="absolute bottom-1 right-1">
							↑
						</Button>
					</div>
				)}

				{quiz.error && (
					<p id="maqsy-quiz-error" role="alert" className="text-xs text-red-600 dark:text-red-400">
						{quiz.error}
					</p>
				)}
			</form>

			<div className="flex items-center justify-between gap-1 px-2 pb-2">
				<div className="flex gap-1">
					<Button type="button" className={ghostButton} disabled={disabled} onClick={quiz.pause} title={strings.pauseHint}>
						{strings.pause}
					</Button>
					{active.quiz.allowRestart && (
						<Button type="button" className={ghostButton} disabled={disabled} onClick={() => quiz.restart()}>
							{strings.restart}
						</Button>
					)}
					<Button type="button" className={ghostButton} disabled={disabled} onClick={quiz.exit}>
						{strings.exit}
					</Button>
				</div>
				{!question.required && (
					<Button type="button" className={ghostButton} disabled={disabled} onClick={quiz.skip}>
						{strings.skip} →
					</Button>
				)}
			</div>
		</div>
	);
}
