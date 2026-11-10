import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import { t } from './util';

/** A trade offer arrived, or yours was accepted, declined or could no longer go through. */
export class TradeNotification extends Notification {
  icon() {
    return 'fas fa-right-left';
  }

  href() {
    return app.route('stickers') + '?tab=trades';
  }

  content() {
    const notification = this.attrs.notification;
    const kind = notification.content()?.kind || 'offered';
    return t(`notification.${kind}`, { username: notification.fromUser()?.displayName() });
  }

  excerpt() {
    return null;
  }
}
