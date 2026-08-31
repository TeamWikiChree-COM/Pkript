// vote - 投票
//
//   #vote(質問){{
//   選択肢1
//   選択肢2
//   }}
//
// 集計は :config/pkript/data/vote/<ページ名>/<質問> に保存する。
// 一度投票した人は、ログインしていれば二重投票にならない。

const MAX_CHOICES = 20;

/** 保存キー。ページごと・質問ごとに分ける。 */
const keyFor = (page, title) => "vote/" + slug(page) + "/" + slug(title);

/** キーに使える文字だけ残す。空になったら固定の名前にする。 */
const slug = (text) => {
	const kept = text.replace(/[^A-Za-z0-9_-]+/g, "-").replace(/^-+|-+$/g, "");
	return kept == "" ? "x" : kept.slice(0, 40);
};

const parseChoices = (body) => {
	const out = [];
	for (const line of body.split("\n")) {
		const choice = line.trim();
		if (choice != "" && !out.includes(choice) && out.length < MAX_CHOICES) {
			out.push(choice);
		}
	}
	return out;
};

/** 保存済みの集計。形が違えば空から始める。 */
const loadTally = (key) => {
	const saved = data.get(key);
	return saved == null ? {counts: {}, voters: []} : saved;
};

const countOf = (tally, choice) =>
	Object.has(tally.counts, choice) ? Number(tally.counts[choice]) : 0;

const totalOf = (tally, choices) =>
	choices.reduce((sum, choice) => sum + countOf(tally, choice), 0);

function plugin_vote_convert(e) {
	const title = e.args[0] || "投票";
	const choices = parseChoices(e.body);
	if (choices.length == 0) {
		return <p class="vote-error">選択肢がありません</p>;
	}

	const key = keyFor(e.page, title);
	const tally = loadTally(key);
	const total = totalOf(tally, choices);
	const voted = hasVoted(tally, e.user.name);

	return <div class="vote">
		<h4>{title}</h4>
		{voted ? <p class="vote-done">投票済みです</p> : voteForm(e, title, choices)}
		<table class="vote-result">
			{choices.map((choice) => resultRow(choice, countOf(tally, choice), total))}
		</table>
		<p class="vote-total">合計 {total} 票</p>
	</div>;
}

const hasVoted = (tally, name) => name != "" && tally.voters.includes(name);

const voteForm = (e, title, choices) => {
	if (!data.canWrite(keyFor(e.page, title))) {
		return <p class="vote-closed">この環境では投票を受け付けられません</p>;
	}
	return <form method="post" action={wiki.uri()}>
		<input type="hidden" name="plugin" value="pkript" />
		<input type="hidden" name="script" value="vote" />
		<input type="hidden" name="pkript_token" value={wiki.token()} />
		<input type="hidden" name="page" value={e.page} />
		<input type="hidden" name="title" value={title} />
		{choices.map((choice) =>
			<label><input type="radio" name="choice" value={choice} /> {choice}</label>)}
		<input type="submit" value="投票する" />
	</form>;
};

const resultRow = (choice, count, total) => {
	const percent = total == 0 ? 0 : Math.round(count * 1000 / total) / 10;
	return <tr>
		<th>{choice}</th>
		<td>
			<span class="vote-bar" style={"display:inline-block; height:0.8em; " +
				"background-color:#69c; width:" + percent + "%"}></span>
		</td>
		<td>{count} 票 ({percent}%)</td>
	</tr>;
};

function plugin_vote_action(e) {
	const page = e.vars["page"];
	const title = e.vars["title"];
	const choice = e.vars["choice"];

	if (page == "" || title == "" || choice == "") {
		return <p class="vote-error">投票内容が不足しています</p>;
	}

	const key = keyFor(page, title);
	const tally = loadTally(key);
	if (hasVoted(tally, e.user.name)) {
		return <p class="vote-error">すでに投票済みです</p>;
	}

	tally.counts[choice] = countOf(tally, choice) + 1;
	if (e.user.name != "") {
		tally.voters.push(e.user.name);
	}
	data.set(key, tally);

	return <div class="vote">
		<p>{choice} に投票しました。</p>
		<p><a href={wiki.uri(page)}>{page} に戻る</a></p>
	</div>;
}
