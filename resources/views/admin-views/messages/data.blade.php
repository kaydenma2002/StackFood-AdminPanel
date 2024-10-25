<div class="chat-container">
@foreach($conversations as $conv)
    @php($user = $conv->sender_type == 'admin' ? $conv->receiver : $conv->sender)
    @if ($user)
    @php($unchecked = ($conv->last_message?->sender_id == $user->id) ? $conv->unread_message_count : 0)

    <div class="chat-user-info d-flex border-bottom p-3 align-items-center customer-list view-admin-conv {{$unchecked ? 'new-msg ' : ''}} {{$unchecked ? 'conv-active' : ''}}"
        onclick="viewAdminConvs('{{ route('admin.message.view', ['conversation_id' => $conv->id, 'user_id' => $user->id]) }}','customer-{{$user->id}}','{{ $conv->id }}','{{ $user->id }}')"
        id="customer-{{$user->id}}">

        <div class="chat-user-info-img d-none d-md-block">
            <img class="avatar-img onerror-image"
                 src="{{ $user['image_full_url'] }}"
                 data-onerror-image="{{ dynamicAsset('public/assets/admin') }}/img/160x160/img1.jpg"
                 alt="Image Description">
        </div>

        <div class="chat-user-info-content">
            <h5 class="mb-0 d-flex justify-content-between">
                <span class="mr-3">{{ $user['f_name'] ?? '' . ' ' . $user['l_name'] ?? '' }}</span>
                <span class="{{$unchecked ? 'badge badge-info' : ''}}">{{ $unchecked ? $unchecked : '' }}</span>
                <small>
                    {{ \App\CentralLogics\Helpers::time_format($conv->last_message?->created_at) }}
                </small>
            </h5>
            <small>{{ $user['phone'] ?? '' }}</small>
            <div class="text-title">{{ Str::limit($conv->last_message?->message ?? '', 35, '...') }}</div>
        </div>
    </div>
    @else
    <div class="chat-user-info d-flex border-bottom p-3 align-items-center customer-list">
        <div class="chat-user-info-img d-none d-md-block">
            <img class="avatar-img"
                 src="{{ dynamicAsset('public/assets/admin') }}/img/160x160/img1.jpg"
                 alt="Image Description">
        </div>
        <div class="chat-user-info-content">
            <h5 class="mb-0 d-flex justify-content-between">
                <span class="mr-3">{{ translate('messages.user_not_found') }}</span>
            </h5>
        </div>
    </div>
    @endif
@endforeach
</div>
<script src="{{dynamicAsset('public/assets/admin')}}/js/view-pages/common.js"></script>
<script src="https://js.pusher.com/7.0/pusher.min.js"></script>
<script>
    // Initialize Pusher with your environment variables for security
    const pusherKey = "{{ env('PUSHER_APP_KEY') }}";
    const pushercluster = "{{ env('PUSHER_APP_CLUSTER') }}";

    const pusher = new Pusher(pusherKey, {
        cluster: pushercluster,
        encrypted: true, // Use encryption for added security
    });

    // Subscribe to the 'orders' channel
    const channel = pusher.subscribe('chat');


    channel.bind('message.sent', function(data) {
        // Log the data received from the event (optional)
        console.log('New message received:', data);

        // Show the modal notification when a new order is placed
        appendNewMessage(data.message, data.user);
    });

    function appendNewMessage(message, user) {
        const chatContainer = document.querySelector('.chat-container');
        const newMessageElement = document.createElement('div');
        newMessageElement.classList.add('chat-user-info', 'd-flex', 'border-bottom', 'p-3', 'align-items-center', 'customer-list', 'view-admin-conv');

        newMessageElement.innerHTML = `
            <div class="chat-user-info-img d-none d-md-block">
                <img class="avatar-img" src="${user.image_full_url}" alt="Image Description">
            </div>
            <div class="chat-user-info-content">
                <h5 class="mb-0 d-flex justify-content-between">
                    <span class="mr-3">${user.f_name ?? ''} ${user.l_name ?? ''}</span>
                    <span class="badge badge-info">New</span>
                    <small>${message.created_at}</small>
                </h5>
                <small>${user.phone ?? ''}</small>
                <div class="text-title">${message.content}</div>
            </div>
        `;

        chatContainer.appendChild(newMessageElement);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
</script>
