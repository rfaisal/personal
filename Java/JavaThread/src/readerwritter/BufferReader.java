package readerwritter;

public class BufferReader implements Runnable {
	private Buffer buffer;
	public BufferReader(Buffer buffer){
		this.buffer=buffer;
	}
	public int read() throws InterruptedException{
		return buffer.read();
	}
	@Override
	public void run() {
		while(true){
			try {
				System.out.println("Read: "+read());
			} catch (InterruptedException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			}
		}
		
	}
}
