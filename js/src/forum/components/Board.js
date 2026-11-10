import app from 'flarum/forum/app';
import Avatar from 'flarum/common/components/Avatar';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import username from 'flarum/common/helpers/username';
import { call, pushUsers, t, user } from '../util';

const MEDALS = ['🥇', '🥈', '🥉'];

/** The members with the most different stickers. */
export default class Board {
  oninit() {
    this.data = null;
    call('GET', '/board', undefined, true)
      .then((data) => {
        pushUsers(data.users);
        this.data = data;
      })
      .catch(() => (this.data = { board: [], total: 0 }))
      .finally(() => m.redraw());
  }

  view() {
    if (!this.data) return <LoadingIndicator display="block" />;
    const { board, total } = this.data;
    if (!board.length) return <p className="StickerTrades-empty">{t('board.empty')}</p>;

    return (
      <ol className="StickerBoard">
        {board.map(([id, unique], i) => {
          const u = user(id);
          return (
            <li className="StickerBoard-row">
              <span className="StickerBoard-rank">{MEDALS[i] || i + 1}</span>
              <Link href={u ? app.route('user.stickers', { username: u.slug() }) : '#'} className="StickerBoard-who">
                <Avatar user={u} /> <span>{username(u)}</span>
              </Link>
              <span className="StickerBoard-bar">
                <span style={{ width: (total ? Math.round((unique / total) * 100) : 0) + '%' }} />
              </span>
              <span className="StickerBoard-n">{t('board.count', { count: unique, total })}</span>
            </li>
          );
        })}
      </ol>
    );
  }
}
