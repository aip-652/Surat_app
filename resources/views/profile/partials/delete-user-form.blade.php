<section class="space-y-6">
  <header>
    <h2 class="text-lg font-medium text-gray-900">
      {{ __('Hapus User') }}
    </h2>

    <p class="mt-1 text-sm text-gray-600">
      {{ __('Setelah user dihapus, user tidak akan dapat login, tetapi nomor surat yang dibuat tetap tersimpan.') }}
    </p>
  </header>

  <x-danger-button type="submit"
    x-data=""
    x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">{{ __('Hapus') }}</x-danger-button>

  <x-modal name="confirm-user-deletion" focusable>
    <form method="post" action="{{ route('users.destroy', $user->id) }}" class="p-6">
      @csrf
      @method('delete')

      <h2 class="text-lg font-medium text-gray-900">
        {{ __('Yakin akan menghapus user ini?') }}
      </h2>

      <div class="mt-6 flex justify-end">
        <x-secondary-button x-on:click="$dispatch('close')">
          {{ __('Batal') }}
        </x-secondary-button>

        <x-danger-button class="ms-3">
          {{ __('Hapus User') }}
        </x-danger-button>
      </div>
    </form>
  </x-modal>
</section>