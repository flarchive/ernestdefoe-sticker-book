import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import useStyles from '../styles';
import { call, t } from '../util';
import Album, { isOwnAlbum } from './Album';

/** Profile → Stickers: a member's album, and their spares to ask for. */
export default class UserStickersPage extends UserPage {
  oninit(vnode) {
    super.oninit(vnode);
    useStyles();
    this.album = null;
    this.loadUser(m.route.param('username'));
    this.onChange = () => this.user && this.fetch(this.user);
    window.addEventListener('sticker-book:changed', this.onChange);
  }

  onremove(vnode) {
    super.onremove(vnode);
    window.removeEventListener('sticker-book:changed', this.onChange);
  }

  show(user) {
    super.show(user);
    if (this.shownFor === user.id()) return;
    this.shownFor = user.id();
    this.fetch(user);
  }

  fetch(user) {
    call('GET', '/album', { user: user.slug() }, true)
      .then((album) => (this.album = album))
      .catch(() => (this.album = { sets: [], stats: {} }))
      .finally(() => m.redraw());
  }

  content() {
    if (!this.album) return <LoadingIndicator display="block" />;
    const stats = this.album.stats;
    return (
      <div className="StickerUserPage">
        {stats?.total ? (
          <p className="StickerUserPage-summary">{t('profile.summary', { count: stats.unique, total: stats.total, sets: stats.completed })}</p>
        ) : null}
        <Album album={this.album} own={isOwnAlbum(this.album)} />
      </div>
    );
  }
}
