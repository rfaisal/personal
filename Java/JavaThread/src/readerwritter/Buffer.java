package readerwritter;

public class Buffer {
	private int []buffer;
	private int index;
	public Buffer(int size){
		buffer= new int[size];
		index=0;
	}
	public synchronized int read() throws InterruptedException{
		if(index==0) wait();
		int ret=buffer[index];
		index--;
		notifyAll();
		return ret;
	}
	public synchronized void write(int num) throws InterruptedException{
		if(index==buffer.length-1) wait();
		buffer[++index]=num;
		notifyAll();
	}
}
